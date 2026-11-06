<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Batch barang per cabang. `qty_on_hand` (satuan dasar) adalah cache dari baris mutasi yang tercatat;
 * hanya diubah oleh StockLedger.
 *
 * @property int $id
 * @property int $item_id
 * @property int $branch_id
 * @property string|null $batch_number
 * @property Carbon|null $expired_at
 * @property int $unit_cost
 * @property string $qty_on_hand
 * @property Carbon|null $received_at
 * @property bool $is_unbatched
 * @property-read Item $item
 * @property-read Branch $branch
 */
#[Fillable(['item_id', 'branch_id', 'batch_number', 'expired_at', 'unit_cost', 'qty_on_hand', 'received_at', 'is_unbatched'])]
class ItemBatch extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['qty_on_hand' => 0, 'unit_cost' => 0, 'is_unbatched' => false];

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<StockMovementLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class);
    }

    public function scopeInStock(Builder $query): void
    {
        $query->where('qty_on_hand', '>', 0);
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->lt(today());
    }

    public function daysToExpiry(): ?int
    {
        return $this->expired_at === null ? null : (int) today()->diffInDays($this->expired_at, false);
    }

    public function label(): string
    {
        if ($this->is_unbatched) {
            return 'Tanpa batch';
        }

        return trim(($this->batch_number ?: '—').($this->expired_at ? ' · ED '.$this->expired_at->format('m/Y') : ''));
    }

    protected function casts(): array
    {
        return [
            'expired_at' => 'date',
            'received_at' => 'date',
            'unit_cost' => 'integer',
            'qty_on_hand' => 'decimal:3',
            'is_unbatched' => 'boolean',
        ];
    }
}
