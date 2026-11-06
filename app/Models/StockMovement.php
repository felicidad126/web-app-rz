<?php

namespace App\Models;

use App\Enums\StockMovementStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReason;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Dokumen mutasi stok (header). Barisnya per batch. Setelah tercatat (posted) tidak bisa diubah;
 * koreksi lewat mutasi pembatalan atau koreksi opname.
 *
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property StockMovementType $type
 * @property StockMovementStatus $status
 * @property Carbon $movement_date
 * @property int|null $supplier_id
 * @property string|null $supplier_invoice_no
 * @property int|null $counterpart_branch_id
 * @property int|null $reverses_movement_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property StockReason|null $reason
 * @property string|null $notes
 * @property int $total_cost
 * @property int|null $created_by
 * @property Carbon|null $submitted_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejected_reason
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 * @property-read Branch $branch
 */
#[Fillable([
    'number', 'branch_id', 'type', 'status', 'movement_date', 'supplier_id', 'supplier_invoice_no', 'counterpart_branch_id',
    'reverses_movement_id', 'source_type', 'source_id', 'reason', 'notes',
])]
class StockMovement extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'total_cost' => 0];

    protected static function booted(): void
    {
        static::updating(function (StockMovement $movement): void {
            $was = $movement->getOriginal('status');
            $wasPosted = $was === StockMovementStatus::Posted || $was === StockMovementStatus::Posted->value;

            if ($wasPosted) {
                throw new LogicException('Mutasi stok yang sudah tercatat tidak bisa diubah. Buat mutasi pembatalan atau koreksi.');
            }
        });

        static::deleting(fn () => throw new LogicException('Mutasi stok tidak bisa dihapus.'));
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function counterpartBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'counterpart_branch_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** @return HasMany<StockMovementLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class)->orderBy('id');
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'reverses_movement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPosted(): bool
    {
        return $this->status === StockMovementStatus::Posted;
    }

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'status' => StockMovementStatus::class,
            'reason' => StockReason::class,
            'movement_date' => 'date',
            'total_cost' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }
}
