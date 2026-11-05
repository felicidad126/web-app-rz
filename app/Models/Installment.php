<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu baris jadwal cicilan paket. Ditagih lewat tagihan biasa; statusnya mengikuti tagihan itu.
 *
 * @property int $id
 * @property int $patient_package_id
 * @property int $sequence
 * @property Carbon $due_date
 * @property int $amount
 * @property int|null $invoice_id
 * @property InstallmentStatus $status
 * @property Carbon|null $paid_at
 * @property-read PatientPackage $patientPackage
 * @property-read Invoice|null $invoice
 */
#[Fillable(['patient_package_id', 'sequence', 'due_date', 'amount', 'invoice_id', 'status', 'paid_at'])]
class Installment extends Model
{
    use LogsAudit;

    /** @return BelongsTo<PatientPackage, $this> */
    public function patientPackage(): BelongsTo
    {
        return $this->belongsTo(PatientPackage::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function label(): string
    {
        return $this->sequence === 0 ? 'Uang muka (DP)' : 'Cicilan ke-'.$this->sequence;
    }

    public function isOverdue(): bool
    {
        return $this->status !== InstallmentStatus::Paid && $this->status !== InstallmentStatus::Cancelled
            && $this->due_date->lt(today());
    }

    public function syncFromInvoice(Invoice $invoice): void
    {
        $this->forceFill(match ($invoice->status) {
            InvoiceStatus::Paid => ['status' => InstallmentStatus::Paid, 'paid_at' => $invoice->paid_at ?? now()],
            InvoiceStatus::Void => ['status' => InstallmentStatus::Pending, 'invoice_id' => null, 'paid_at' => null],
            default => ['status' => InstallmentStatus::Billed, 'paid_at' => null],
        })->save();
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date',
            'amount' => 'integer',
            'status' => InstallmentStatus::class,
            'paid_at' => 'datetime',
        ];
    }
}
