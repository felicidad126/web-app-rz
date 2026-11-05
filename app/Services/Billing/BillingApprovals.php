<?php

namespace App\Services\Billing;

use App\Enums\DepositTransactionType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Enums\VisitStatus;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\PackageUsage;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Void & refund dengan persetujuan (Planning v2 §4.3: kasir mengajukan, owner menyetujui).
 *
 * - Void pembayaran: koreksi salah input (metode/nominal). Uang tidak berpindah.
 * - Void tagihan: hanya bila tidak ada pembayaran aktif. Pemakaian kuota paket dibatalkan.
 * - Refund: uang dikembalikan ke pasien. Disetujui owner, lalu diserahkan kasir dari sesi yang terbuka.
 *
 * Yang menyetujui tidak boleh orang yang sama dengan yang mengajukan.
 */
final class BillingApprovals
{
    public static function requestPaymentVoid(Payment $payment, string $reason, User $user): void
    {
        if ($payment->status !== PaymentStatus::Active) {
            throw new InvalidArgumentException('Pembayaran ini sudah '.$payment->status->getLabel().'.');
        }

        self::requireReason($reason);

        $payment->forceFill([
            'status' => PaymentStatus::VoidRequested,
            'void_reason' => $reason,
            'void_requested_by' => $user->id,
            'void_requested_at' => now(),
        ])->save();
    }

    public static function decidePaymentVoid(Payment $payment, bool $approve, User $approver): void
    {
        if ($payment->status !== PaymentStatus::VoidRequested) {
            throw new InvalidArgumentException('Tidak ada pengajuan void untuk pembayaran ini.');
        }

        self::ensureFourEyes($payment->void_requested_by, $approver);

        DB::transaction(function () use ($payment, $approve, $approver): void {
            if (! $approve) {
                $payment->forceFill(['status' => PaymentStatus::Active])->save();

                return;
            }

            $payment->forceFill(['status' => PaymentStatus::Void, 'voided_by' => $approver->id, 'voided_at' => now()])->save();
            $payment->loadMissing('invoice.patient');
            $invoice = $payment->invoice;

            if ($payment->method === PaymentMethod::Deposit) {
                DepositLedger::append($invoice->patient, $invoice->branch_id, DepositTransactionType::Reversal, $payment->amount, [
                    'payment_id' => $payment->id,
                    'notes' => 'Void pembayaran '.$invoice->label(),
                ]);
            }

            $invoice->syncPaymentStatus();
        });
    }

    public static function requestInvoiceVoid(Invoice $invoice, string $reason, User $user): void
    {
        self::requireReason($reason);

        if ($invoice->isDraft()) {
            InvoiceBuilder::cancelDraft($invoice, $reason, $user);

            return;
        }

        if ($invoice->status === InvoiceStatus::Void || $invoice->void_requested_at !== null) {
            throw new InvalidArgumentException('Tagihan ini sudah void atau sedang menunggu persetujuan void.');
        }

        self::ensureNoMoney($invoice);

        $invoice->forceFill(['void_reason' => $reason, 'void_requested_by' => $user->id, 'void_requested_at' => now()])->save();
    }

    public static function decideInvoiceVoid(Invoice $invoice, bool $approve, User $approver): void
    {
        if ($invoice->void_requested_at === null || $invoice->status === InvoiceStatus::Void) {
            throw new InvalidArgumentException('Tidak ada pengajuan void untuk tagihan ini.');
        }

        self::ensureFourEyes($invoice->void_requested_by, $approver);

        DB::transaction(function () use ($invoice, $approve, $approver): void {
            if (! $approve) {
                $invoice->forceFill(['void_requested_by' => null, 'void_requested_at' => null])->save();

                return;
            }

            self::ensureNoMoney($invoice);

            $invoice->forceFill(['status' => InvoiceStatus::Void, 'voided_by' => $approver->id, 'voided_at' => now()])->save();

            PackageUsage::query()->whereIn('invoice_item_id', $invoice->items()->select('id'))->whereNull('voided_at')->update(['voided_at' => now()]);

            foreach ($invoice->installments()->get() as $installment) {
                $installment->syncFromInvoice($invoice);
            }

            if ($invoice->visit_id !== null) {
                /** @var Visit $visit */
                $visit = Visit::with('medicalRecord')->findOrFail($invoice->visit_id);
                $visit->update(['status' => $visit->isLocked() ? VisitStatus::Done : VisitStatus::InTreatment]);
            }
        });
    }

    /** Sisa dana yang masih bisa direfund dari satu tagihan (dibayar − refund yang belum ditolak). */
    public static function refundableFromInvoice(Invoice $invoice): int
    {
        $open = (int) $invoice->refunds()->whereIn('status', [RefundStatus::Pending, RefundStatus::Approved])->sum('amount');

        return max(0, $invoice->paid_total - $open);
    }

    public static function requestRefund(
        Patient $patient,
        RefundSource $source,
        int $amount,
        PaymentMethod $method,
        string $reason,
        User $user,
        int $branchId,
        ?Invoice $invoice = null,
    ): Refund {
        self::requireReason($reason);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal refund harus lebih dari 0.');
        }

        if ($source === RefundSource::Invoice) {
            if ($invoice === null || $invoice->patient_id !== $patient->id) {
                throw new InvalidArgumentException('Pilih tagihan yang direfund.');
            }

            if ($amount > self::refundableFromInvoice($invoice)) {
                throw new InvalidArgumentException('Refund melebihi dana yang sudah dibayar untuk tagihan ini.');
            }
        } else {
            if ($method === PaymentMethod::Deposit) {
                throw new InvalidArgumentException('Refund deposit dikembalikan dalam bentuk uang, bukan deposit.');
            }

            $pending = (int) Refund::where('patient_id', $patient->id)->where('source', RefundSource::Deposit)
                ->whereIn('status', [RefundStatus::Pending, RefundStatus::Approved])->sum('amount');

            if ($amount > $patient->depositBalance() - $pending) {
                throw new InvalidArgumentException('Refund melebihi saldo deposit.');
            }
        }

        return Refund::create([
            'branch_id' => $branchId,
            'patient_id' => $patient->id,
            'invoice_id' => $invoice?->id,
            'source' => $source,
            'amount' => $amount,
            'method' => $method,
            'reason' => $reason,
            'requested_by' => $user->id,
            'requested_at' => now(),
        ]);
    }

    public static function decideRefund(Refund $refund, bool $approve, User $approver, ?string $note = null): void
    {
        if ($refund->status !== RefundStatus::Pending) {
            throw new InvalidArgumentException('Refund ini sudah diputuskan.');
        }

        self::ensureFourEyes($refund->requested_by, $approver);

        $refund->forceFill([
            'status' => $approve ? RefundStatus::Approved : RefundStatus::Rejected,
            'decided_by' => $approver->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();
    }

    /** Kasir menyerahkan dana refund yang sudah disetujui (tercatat di sesi kasirnya). */
    public static function payOutRefund(Refund $refund, CashSession $session, User $user): void
    {
        if ($refund->status !== RefundStatus::Approved) {
            throw new InvalidArgumentException('Refund belum disetujui.');
        }

        CashSessions::ensureOpen($session);

        DB::transaction(function () use ($refund, $session, $user): void {
            $refund->forceFill([
                'status' => RefundStatus::Paid,
                'paid_by' => $user->id,
                'paid_at' => now(),
                'cash_session_id' => $refund->method->isMoneyIn() ? $session->id : null,
            ])->save();

            $refund->loadMissing('patient', 'invoice');

            if ($refund->source === RefundSource::Deposit) {
                DepositLedger::append($refund->patient, $session->branch_id, DepositTransactionType::Withdrawal, -$refund->amount, [
                    'refund_id' => $refund->id,
                    'notes' => 'Refund deposit: '.$refund->reason,
                ]);

                return;
            }

            if ($refund->method === PaymentMethod::Deposit) {
                DepositLedger::append($refund->patient, $session->branch_id, DepositTransactionType::RefundCredit, $refund->amount, [
                    'refund_id' => $refund->id,
                    'notes' => 'Refund '.$refund->invoice?->label().' ke deposit',
                ]);
            }

            $refund->invoice?->syncPaymentStatus();
        });
    }

    private static function ensureNoMoney(Invoice $invoice): void
    {
        $active = $invoice->payments()->where('status', '!=', PaymentStatus::Void)->exists();

        if ($active && $invoice->paid_total > 0) {
            throw new InvalidArgumentException('Tagihan masih punya pembayaran. Void pembayaran (salah input) atau refund dulu, baru void tagihan.');
        }
    }

    private static function ensureFourEyes(?int $requestedBy, User $approver): void
    {
        if (! $approver->can('Approve:Billing')) {
            throw new InvalidArgumentException('Hanya owner yang bisa menyetujui void/refund.');
        }

        if ($requestedBy !== null && $requestedBy === $approver->id) {
            throw new InvalidArgumentException('Pengajuan tidak bisa disetujui oleh orang yang sama.');
        }
    }

    private static function requireReason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 5) {
            throw new InvalidArgumentException('Tulis alasan yang jelas (minimal 5 karakter).');
        }
    }
}
