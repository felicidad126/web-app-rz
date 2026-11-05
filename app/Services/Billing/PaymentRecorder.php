<?php

namespace App\Services\Billing;

use App\Enums\DepositTransactionType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Terima pembayaran untuk satu atau beberapa tagihan pasien sekaligus.
 *
 * Draf yang ikut dibayar diterbitkan dulu. Uang dialokasikan ke tagihan terlama lebih dulu,
 * dengan urutan metode seperti yang diisi kasir. Tunai boleh lebih (kembalian); metode lain tidak.
 */
final class PaymentRecorder
{
    /**
     * @param  list<int>  $invoiceIds
     * @param  list<array{method: PaymentMethod|string, amount: int|string, reference_no?: string|null}>  $lines
     */
    public static function receive(
        Patient $patient,
        array $invoiceIds,
        array $lines,
        CashSession $session,
        User $user,
        ?int $cashTendered = null,
    ): PaymentTransaction {
        CashSessions::ensureOpen($session);

        $lines = collect($lines)
            ->map(fn (array $line): array => [
                'method' => $line['method'] instanceof PaymentMethod ? $line['method'] : PaymentMethod::from($line['method']),
                'amount' => (int) $line['amount'],
                'reference_no' => $line['reference_no'] ?? null,
            ])
            ->filter(fn (array $line): bool => $line['amount'] > 0)
            ->values();

        if ($lines->isEmpty()) {
            throw new InvalidArgumentException('Isi minimal satu pembayaran.');
        }

        return DB::transaction(function () use ($patient, $invoiceIds, $lines, $session, $user, $cashTendered): PaymentTransaction {
            $invoices = self::lockInvoices($patient, $invoiceIds, $user);
            $due = (int) $invoices->sum(fn (Invoice $invoice): int => $invoice->balance());
            $paying = (int) $lines->sum('amount');

            if ($paying > $due) {
                throw new InvalidArgumentException('Pembayaran Rp'.self::rupiah($paying).' melebihi sisa tagihan Rp'.self::rupiah($due).'. Untuk tunai, isi "uang diterima" agar kembalian dihitung.');
            }

            $cash = (int) $lines->where('method', PaymentMethod::Cash)->sum('amount');
            $change = null;

            if ($cashTendered !== null && $cash > 0) {
                if ($cashTendered < $cash) {
                    throw new InvalidArgumentException('Uang tunai diterima kurang dari jumlah tunai yang dibayarkan.');
                }

                $change = $cashTendered - $cash;
            }

            $deposit = (int) $lines->where('method', PaymentMethod::Deposit)->sum('amount');

            if ($deposit > 0 && $deposit > $patient->depositBalance()) {
                throw new InvalidArgumentException('Saldo deposit tidak cukup (saldo Rp'.self::rupiah($patient->depositBalance()).').');
            }

            $transaction = PaymentTransaction::create([
                'patient_id' => $patient->id,
                'branch_id' => $session->branch_id,
                'cash_session_id' => $session->id,
                'received_by' => $user->id,
                'paid_at' => now(),
                'total' => $paying,
                'cash_tendered' => $cash > 0 ? ($cashTendered ?? $cash) : null,
                'change_given' => $cash > 0 ? ($change ?? 0) : null,
            ]);

            $remaining = $invoices->mapWithKeys(fn (Invoice $invoice): array => [$invoice->id => $invoice->balance()])->all();

            foreach ($lines as $line) {
                $left = $line['amount'];

                foreach ($remaining as $invoiceId => $balance) {
                    if ($left === 0) {
                        break;
                    }

                    if ($balance === 0) {
                        continue;
                    }

                    $amount = min($left, $balance);
                    $remaining[$invoiceId] -= $amount;
                    $left -= $amount;

                    $payment = Payment::create([
                        'payment_transaction_id' => $transaction->id,
                        'invoice_id' => $invoiceId,
                        'method' => $line['method'],
                        'amount' => $amount,
                        'reference_no' => $line['reference_no'],
                    ]);

                    if ($line['method'] === PaymentMethod::Deposit) {
                        DepositLedger::append($patient, $session->branch_id, DepositTransactionType::Usage, -$amount, [
                            'payment_id' => $payment->id,
                            'notes' => 'Bayar '.$invoices->firstWhere('id', $invoiceId)?->label(),
                        ]);
                    }
                }
            }

            $invoices->each(fn (Invoice $invoice) => $invoice->refresh()->syncPaymentStatus());

            return $transaction;
        });
    }

    /**
     * Kunci tagihan yang dibayar, terbitkan draf, urutkan dari yang terlama.
     *
     * @param  list<int>  $invoiceIds
     * @return Collection<int, Invoice>
     */
    private static function lockInvoices(Patient $patient, array $invoiceIds, User $user): Collection
    {
        if ($invoiceIds === []) {
            throw new InvalidArgumentException('Pilih tagihan yang dibayar.');
        }

        $invoices = Invoice::query()->whereKey($invoiceIds)->lockForUpdate()->get();

        if ($invoices->count() !== count(array_unique($invoiceIds)) || $invoices->contains(fn (Invoice $i): bool => $i->patient_id !== $patient->id)) {
            throw new InvalidArgumentException('Tagihan tidak ditemukan untuk pasien ini.');
        }

        foreach ($invoices as $invoice) {
            if ($invoice->isDraft()) {
                InvoiceBuilder::issue($invoice, $user);
            }
        }

        return Invoice::query()->whereKey($invoiceIds)->get()
            // Draf yang seluruhnya ditanggung paket/penjamin langsung lunas saat diterbitkan.
            ->reject(fn (Invoice $invoice): bool => $invoice->status === InvoiceStatus::Paid && $invoice->patient_portion === 0)
            ->each(function (Invoice $invoice): void {
                if (! $invoice->isOutstanding()) {
                    throw new InvalidArgumentException('Tagihan '.$invoice->label().' berstatus '.$invoice->status->getLabel().', tidak bisa dibayar.');
                }
            })
            ->sortBy([['issued_at', 'asc'], ['id', 'asc']])
            ->values();
    }

    private static function rupiah(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
