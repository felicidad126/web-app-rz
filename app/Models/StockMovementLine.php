<?php

namespace App\Models;

use App\Enums\StockMovementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Baris mutasi per batch. `qty` dalam satuan dasar, bertanda (+ masuk, − keluar).
 *
 * @property int $id
 * @property int $stock_movement_id
 * @property int $item_id
 * @property int|null $item_batch_id
 * @property int|null $visit_procedure_id
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property string $qty
 * @property int $unit_cost
 * @property int $line_cost
 * @property string|null $batch_number
 * @property Carbon|null $expired_at
 * @property string|null $notes
 * @property-read StockMovement $movement
 * @property-read Item $item
 * @property-read ItemBatch|null $batch
 * @property-read Unit $enteredUnit
 */
#[Fillable([
    'stock_movement_id', 'item_id', 'item_batch_id', 'visit_procedure_id', 'entered_qty', 'entered_unit_id', 'qty',
    'unit_cost', 'line_cost', 'batch_number', 'expired_at', 'notes',
])]
class StockMovementLine extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['unit_cost' => 0, 'line_cost' => 0];

    protected static function booted(): void
    {
        $guard = function (StockMovementLine $line): void {
            $status = StockMovement::query()->whereKey($line->stock_movement_id)->toBase()->value('status');

            if ($status === StockMovementStatus::Posted->value && $line->exists) {
                throw new LogicException('Baris mutasi yang sudah tercatat tidak bisa diubah.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
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

    /** @return BelongsTo<Unit, $this> */
    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'entered_unit_id');
    }

    /** @return BelongsTo<VisitProcedure, $this> */
    public function visitProcedure(): BelongsTo
    {
        return $this->belongsTo(VisitProcedure::class);
    }

    protected function casts(): array
    {
        return [
            'entered_qty' => 'decimal:3',
            'qty' => 'decimal:3',
            'unit_cost' => 'integer',
            'line_cost' => 'integer',
            'expired_at' => 'date',
        ];
    }
}
