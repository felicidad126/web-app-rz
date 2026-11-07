<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris pesanan: jumlah & harga dalam satuan pesan (mis. box), konversi ke satuan dasar di-snapshot.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int $item_id
 * @property int $unit_id
 * @property string $factor_to_base
 * @property string $quantity
 * @property string $received_quantity
 * @property int $unit_price
 * @property int $line_total
 * @property int|null $purchase_request_id
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read Item $item
 * @property-read Unit $unit
 * @property-read PurchaseRequest|null $purchaseRequest
 */
#[Fillable(['item_id', 'unit_id', 'factor_to_base', 'quantity', 'received_quantity', 'unit_price', 'line_total', 'purchase_request_id'])]
class PurchaseOrderLine extends Model
{
    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function remainingQuantity(): float
    {
        return max(0.0, round((float) $this->quantity - (float) $this->received_quantity, 3));
    }

    protected function casts(): array
    {
        return [
            'factor_to_base' => 'decimal:4',
            'quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'unit_price' => 'integer',
            'line_total' => 'integer',
        ];
    }
}
