<?php

namespace App\Models;

use App\Enums\ItemType;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * Master obat, bahan dental, alat habis pakai, dan produk retail.
 *
 * @property int $id
 * @property int $base_unit_id
 * @property ItemType $type
 */
#[Fillable(['sku', 'name', 'type', 'kfa_code', 'base_unit_id', 'min_stock', 'track_expiry', 'sell_price', 'is_active'])]
class Item extends Model
{
    use HasFactory;
    use LogsAudit;
    use SoftDeletes;

    /** @return BelongsTo<Unit, $this> */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /** @return HasMany<ItemUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ItemUnit::class);
    }

    /** Konversi jumlah dari satuan tertentu ke satuan dasar. */
    public function toBaseQuantity(float $quantity, int $unitId): float
    {
        if ($unitId === $this->base_unit_id) {
            return $quantity;
        }

        $conversion = $this->units()->where('unit_id', $unitId)->first();

        if ($conversion === null) {
            throw new InvalidArgumentException("Satuan #{$unitId} belum punya konversi untuk {$this->name}.");
        }

        return $quantity * (float) $conversion->factor_to_base;
    }

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'min_stock' => 'decimal:2',
            'track_expiry' => 'boolean',
            'sell_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
