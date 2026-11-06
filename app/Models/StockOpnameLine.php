<?php

namespace App\Models;

use App\Enums\StockOpnameStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Satu baris hitung opname (satuan dasar). Tanpa `item_batch_id` = batch baru yang ditemukan saat hitung.
 *
 * @property int $id
 * @property int $stock_opname_id
 * @property int $item_id
 * @property int|null $item_batch_id
 * @property string|null $batch_number
 * @property Carbon|null $expired_at
 * @property int $unit_cost
 * @property string $system_qty
 * @property string|null $counted_qty
 * @property string|null $notes
 * @property-read Item $item
 * @property-read ItemBatch|null $batch
 */
#[Fillable(['stock_opname_id', 'item_id', 'item_batch_id', 'batch_number', 'expired_at', 'unit_cost', 'system_qty', 'counted_qty', 'notes'])]
class StockOpnameLine extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['system_qty' => 0, 'unit_cost' => 0];

    protected static function booted(): void
    {
        $guard = function (StockOpnameLine $line): void {
            $status = StockOpname::query()->whereKey($line->stock_opname_id)->toBase()->value('status');

            if ($status !== null && $status !== StockOpnameStatus::Counting->value) {
                throw new LogicException('Hasil hitung terkunci setelah opname diajukan.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<StockOpname, $this> */
    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** @return BelongsTo<ItemBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function difference(): ?float
    {
        return $this->counted_qty === null ? null : (float) $this->counted_qty - (float) $this->system_qty;
    }

    protected function casts(): array
    {
        return [
            'expired_at' => 'date',
            'unit_cost' => 'integer',
            'system_qty' => 'decimal:3',
            'counted_qty' => 'decimal:3',
        ];
    }
}
