<?php

namespace App\Models;

use App\Enums\SatuSehatIssue;
use App\Enums\SatuSehatSyncStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Status pengiriman satu data ke SATUSEHAT (saat ini: satu kunjungan sebagai Bundle).
 *
 * @property int $id
 * @property string $syncable_type
 * @property int $syncable_id
 * @property string $resource_type
 * @property int|null $branch_id
 * @property SatuSehatSyncStatus $status
 * @property string|null $satusehat_id
 * @property array<string, array<string, string>>|null $resource_ids
 * @property array<string, int>|null $resource_counts
 * @property int $attempts
 * @property SatuSehatIssue|null $issue_code
 * @property string|null $last_error
 * @property list<string>|null $warnings
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $sent_at
 * @property Carbon $updated_at
 */
#[Fillable(['syncable_type', 'syncable_id', 'resource_type', 'branch_id', 'status'])]
class SatuSehatSync extends Model
{
    protected $table = 'satusehat_syncs';

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    /** @return MorphTo<Model, $this> */
    public function syncable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'syncable_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SatuSehatSyncStatus::class,
            'issue_code' => SatuSehatIssue::class,
            'resource_ids' => 'array',
            'resource_counts' => 'array',
            'warnings' => 'array',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
