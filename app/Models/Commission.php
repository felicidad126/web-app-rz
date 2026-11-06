<?php

namespace App\Models;

use App\Enums\CommissionRole;
use App\Enums\CommissionStatus;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Entri jasa per baris tindakan & per peran. Angka tidak diubah setelah dibuat; void/refund membuat entri
 * pembalik (amount negatif, `reverses_commission_id`). Yang boleh berubah hanya status & rekap.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $invoice_item_id
 * @property int $branch_id
 * @property int $practitioner_id
 * @property CommissionRole $role
 * @property int|null $commission_rule_id
 * @property array<string, mixed> $rule_snapshot
 * @property Carbon $service_date
 * @property int $gross_amount
 * @property int $discount_amount
 * @property int $net_amount
 * @property int $material_cost
 * @property int $lab_cost
 * @property int $base_amount
 * @property int $amount
 * @property CommissionStatus $status
 * @property Carbon|null $earned_at
 * @property int|null $reverses_commission_id
 * @property string|null $reason
 * @property int|null $commission_payout_id
 * @property-read Invoice $invoice
 * @property-read InvoiceItem $invoiceItem
 * @property-read Practitioner $practitioner
 */
#[Fillable([
    'invoice_id', 'invoice_item_id', 'branch_id', 'practitioner_id', 'role', 'commission_rule_id', 'rule_snapshot', 'service_date',
    'gross_amount', 'discount_amount', 'net_amount', 'material_cost', 'lab_cost', 'base_amount', 'amount', 'status', 'earned_at',
    'reverses_commission_id', 'reason', 'commission_payout_id',
])]
class Commission extends Model
{
    use LogsAudit;

    private const FROZEN = ['amount', 'base_amount', 'gross_amount', 'discount_amount', 'net_amount', 'material_cost', 'lab_cost', 'practitioner_id', 'invoice_item_id', 'rule_snapshot'];

    protected static function booted(): void
    {
        static::updating(function (Commission $commission): void {
            foreach (self::FROZEN as $column) {
                if ($commission->isDirty($column)) {
                    throw new LogicException('Angka jasa tidak bisa diubah. Koreksi lewat entri pembalik.');
                }
            }
        });

        static::deleting(fn () => throw new LogicException('Entri jasa tidak bisa dihapus.'));
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<InvoiceItem, $this> */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return BelongsTo<CommissionPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(CommissionPayout::class, 'commission_payout_id');
    }

    /** @return BelongsTo<Commission, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(Commission::class, 'reverses_commission_id');
    }

    public function isReversal(): bool
    {
        return $this->reverses_commission_id !== null;
    }

    protected function casts(): array
    {
        return [
            'role' => CommissionRole::class,
            'status' => CommissionStatus::class,
            'rule_snapshot' => 'array',
            'service_date' => 'date',
            'earned_at' => 'datetime',
            'gross_amount' => 'integer',
            'discount_amount' => 'integer',
            'net_amount' => 'integer',
            'material_cost' => 'integer',
            'lab_cost' => 'integer',
            'base_amount' => 'integer',
            'amount' => 'integer',
        ];
    }
}
