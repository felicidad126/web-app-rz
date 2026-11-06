<?php

namespace App\Models;

use App\Enums\StockOpnameStatus;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Stok opname per cabang: snapshot sistem → hitung fisik → disetujui → mutasi koreksi.
 *
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property StockOpnameStatus $status
 * @property Carbon $started_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $submitted_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejected_reason
 * @property int|null $adjustment_movement_id
 * @property-read Branch $branch
 */
#[Fillable(['number', 'branch_id', 'status', 'started_at', 'notes'])]
class StockOpname extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'counting'];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<StockOpnameLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockOpnameLine::class)->orderBy('id');
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'adjustment_movement_id');
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

    public function isCounting(): bool
    {
        return $this->status === StockOpnameStatus::Counting;
    }

    protected function casts(): array
    {
        return [
            'status' => StockOpnameStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
