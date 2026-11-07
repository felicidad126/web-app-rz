<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Permintaan pembelian satu barang (biasanya dari perawat saat stok menipis). Diproses lewat
 * App\Services\Purchasing\Purchasing: disetujui/ditolak oleh orang lain → dipesan → diterima.
 *
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $item_id
 * @property int $unit_id
 * @property string $quantity
 * @property string $base_quantity
 * @property string|null $reason
 * @property PurchaseRequestStatus $status
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property int|null $purchase_order_id
 * @property Carbon $created_at
 * @property-read Branch $branch
 * @property-read Item $item
 * @property-read Unit $unit
 * @property-read User|null $requester
 * @property-read User|null $decider
 * @property-read PurchaseOrder|null $purchaseOrder
 */
#[Fillable(['number', 'branch_id', 'item_id', 'unit_id', 'quantity', 'base_quantity', 'reason', 'status', 'requested_by', 'decided_by', 'decided_at', 'decision_note', 'purchase_order_id'])]
class PurchaseRequest extends Model
{
    use LogsAudit;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'decided_at' => 'datetime',
        ];
    }
}
