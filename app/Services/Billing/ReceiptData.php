<?php

namespace App\Services\Billing;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Isi struk thermal & nota: dari satu transaksi pembayaran (bisa beberapa tagihan) atau satu tagihan (cetak ulang).
 *
 * @phpstan-type Line array{label: string, amount: int|null, indent?: bool, strong?: bool}
 */
final class ReceiptData
{
    /**
     * @return array{
     *     branch: Branch, title: string, meta: list<array{0: string, 1: string}>,
     *     sections: list<list<array{label: string, amount: int|null, indent?: bool, strong?: bool}>>,
     *     next: Appointment|null, patient: Patient
     * }
     */
    public static function forTransaction(PaymentTransaction $transaction): array
    {
        $transaction->loadMissing(['branch', 'patient', 'receiver', 'payments.invoice.items', 'payments.invoice.visit.practitioner.user']);
        $payments = $transaction->payments->where('status', '!=', PaymentStatus::Void);
        /** @var Collection<int, Invoice> $invoices */
        $invoices = $transaction->payments->pluck('invoice')->unique('id')->sortBy(fn (Invoice $i) => $i->issued_at->timestamp ?? 0)->values();
        $main = $invoices->last();

        $lines = [];
        $billed = [];

        foreach ($invoices as $invoice) {
            $paidNow = (int) $payments->where('invoice_id', $invoice->id)->sum('amount');

            if ($invoice->issued_at?->isSameDay($transaction->paid_at)) {
                foreach ($invoice->items as $item) {
                    $lines = [...$lines, ...self::itemLines($item->description.self::tooth($item->tooth_number, $item->surfaces), (float) $item->qty, $item->unit_price, $item->lineSubtotal())];
                }

                $billed[] = ['label' => 'Tagihan '.$invoice->invoice_number, 'amount' => $paidNow];
            } else {
                $billed[] = ['label' => 'Saldo '.$invoice->invoice_number, 'amount' => $paidNow];
            }
        }

        $billed[] = ['label' => 'TOTAL DIBAYAR', 'amount' => (int) $payments->sum('amount'), 'strong' => true];

        $paymentLines = self::paymentLines($payments, $transaction->cash_tendered, $transaction->change_given);
        $paymentLines[] = ['label' => 'Sisa tagihan', 'amount' => (int) $invoices->sum(fn (Invoice $i): int => $i->fresh()?->balance() ?? 0), 'strong' => true];

        return [
            'branch' => $transaction->branch,
            'patient' => $transaction->patient,
            'title' => 'STRUK PEMBAYARAN',
            'meta' => [
                ['No.', $invoices->pluck('invoice_number')->implode(', ')],
                ['Tanggal', $transaction->paid_at->format('d/m/Y H:i')],
                ['Kasir', $transaction->receiver->name ?? '—'],
                ['Pasien', $transaction->patient->name],
                ['No. RM', $transaction->patient->rm_number],
                ...($main?->visit ? [['Dokter', $main->visit->practitioner->user->name]] : []),
            ],
            'sections' => array_values(array_filter([$lines, $billed, $paymentLines])),
            'next' => self::nextAppointment($transaction->patient_id, $transaction->paid_at),
        ];
    }

    /**
     * @return array{
     *     branch: Branch, title: string, meta: list<array{0: string, 1: string}>,
     *     sections: list<list<array{label: string, amount: int|null, indent?: bool, strong?: bool}>>,
     *     next: Appointment|null, patient: Patient
     * }
     */
    public static function forInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['branch', 'patient', 'items', 'visit.practitioner.user', 'payments.transaction', 'issuer']);
        $payments = $invoice->payments->where('status', '!=', PaymentStatus::Void);

        $lines = [];

        foreach ($invoice->items as $item) {
            $lines = [...$lines, ...self::itemLines($item->description.self::tooth($item->tooth_number, $item->surfaces), (float) $item->qty, $item->unit_price, $item->lineSubtotal())];
        }

        $totals = [['label' => 'Subtotal', 'amount' => $invoice->subtotal]];

        if ($invoice->invoice_discount) {
            $totals[] = ['label' => 'Diskon', 'amount' => -$invoice->invoice_discount];
        }

        if ($invoice->tax_total) {
            $totals[] = ['label' => 'Pajak', 'amount' => $invoice->tax_total];
        }

        if ($invoice->rounding) {
            $totals[] = ['label' => 'Pembulatan', 'amount' => $invoice->rounding];
        }

        if ($invoice->payer_portion) {
            $totals[] = ['label' => 'Ditanggung penjamin', 'amount' => -$invoice->payer_portion];
        }

        $totals[] = ['label' => 'TOTAL', 'amount' => $invoice->patient_portion, 'strong' => true];

        $paymentLines = self::paymentLines($payments, null, null);
        $paymentLines[] = ['label' => 'Sisa tagihan', 'amount' => $invoice->balance(), 'strong' => true];

        return [
            'branch' => $invoice->branch,
            'patient' => $invoice->patient,
            'title' => $invoice->balance() === 0 ? 'STRUK PEMBAYARAN' : 'TAGIHAN',
            'meta' => [
                ['No.', $invoice->label()],
                ['Tanggal', ($invoice->issued_at ?? $invoice->created_at)->format('d/m/Y H:i')],
                ['Pasien', $invoice->patient->name],
                ['No. RM', $invoice->patient->rm_number],
                ...($invoice->visit ? [['Dokter', $invoice->visit->practitioner->user->name]] : []),
            ],
            'sections' => array_values(array_filter([$lines, $totals, $paymentLines])),
            'next' => self::nextAppointment($invoice->patient_id, now()),
        ];
    }

    /** @return list<array{label: string, amount: int|null, indent?: bool}> */
    private static function itemLines(string $label, float $qty, int $unitPrice, int $amount): array
    {
        if ($qty === 1.0) {
            return [['label' => $label, 'amount' => $amount]];
        }

        $qtyLabel = fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : (string) $qty;

        return [
            ['label' => $label, 'amount' => null],
            ['label' => $qtyLabel.' x '.number_format($unitPrice, 0, ',', '.'), 'amount' => $amount, 'indent' => true],
        ];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return list<array{label: string, amount: int|null, indent?: bool}>
     */
    private static function paymentLines(Collection $payments, ?int $tendered, ?int $change): array
    {
        $lines = [];

        foreach ($payments->groupBy(fn (Payment $p): string => $p->method->value.'|'.$p->reference_no) as $group) {
            /** @var Payment $first */
            $first = $group->first();
            $lines[] = ['label' => trim($first->method->getLabel().' '.$first->reference_no), 'amount' => (int) $group->sum('amount')];

            if ($first->method === PaymentMethod::Cash && $tendered !== null) {
                $lines[] = ['label' => 'Diterima', 'amount' => $tendered, 'indent' => true];
                $lines[] = ['label' => 'Kembali', 'amount' => $change ?? 0, 'indent' => true];
            }
        }

        return $lines;
    }

    private static function tooth(?string $tooth, ?string $surfaces): string
    {
        $text = trim(($tooth ?? '').' '.($surfaces ?? ''));

        return $text === '' ? '' : ' '.$text;
    }

    private static function nextAppointment(int $patientId, Carbon $after): ?Appointment
    {
        return Appointment::query()->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::Confirmed])->with(['practitioner.user', 'chair'])
            ->where('patient_id', $patientId)->where('start_at', '>', $after)->orderBy('start_at')->first();
    }
}
