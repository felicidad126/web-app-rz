<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BOM: bahan yang dipakai untuk 1× tindakan (dipotong otomatis saat rekam medis ditandatangani).
 *
 * @property int $id
 * @property int $treatment_id
 * @property int $item_id
 * @property string $qty
 * @property int $unit_id
 * @property-read Item $item
 * @property-read Unit $unit
 */
#[Fillable(['treatment_id', 'item_id', 'qty', 'unit_id'])]
class TreatmentMaterial extends Model
{
    use LogsAudit;

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Jumlah dalam satuan dasar barang untuk 1× tindakan. */
    public function baseQty(): float
    {
        return $this->item->toBaseQuantity((float) $this->qty, $this->unit_id);
    }

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }
}
