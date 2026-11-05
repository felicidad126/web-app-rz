<?php

namespace App\Models;

use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property int|null $branch_id
 * @property int|null $visit_id
 * @property string $title
 * @property TreatmentPlanStatus $status
 * @property string|null $notes
 * @property Carbon|null $presented_at
 * @property Carbon|null $decided_at
 */
#[Fillable(['patient_id', 'practitioner_id', 'branch_id', 'visit_id', 'title', 'status', 'notes', 'presented_at', 'decided_at'])]
class TreatmentPlan extends Model
{
    use LogsAudit;
    use RecordsAuthor;
    use SoftDeletes;

    protected bool $tracksUpdater = true;

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return HasMany<TreatmentPlanItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class)->orderBy('phase')->orderBy('sequence');
    }

    public function totalEstimate(): int
    {
        return (int) $this->items
            ->where('status', '!=', TreatmentPlanItemStatus::Cancelled)
            ->sum('price_estimate');
    }

    /** Status setelah item dikerjakan: sebagian selesai / selesai. */
    public function syncProgress(): void
    {
        if (! in_array($this->status, [TreatmentPlanStatus::Accepted, TreatmentPlanStatus::PartiallyDone], true)) {
            return;
        }

        $items = $this->items()->where('status', '!=', TreatmentPlanItemStatus::Cancelled)->get();

        if ($items->isEmpty()) {
            return;
        }

        $done = $items->where('status', TreatmentPlanItemStatus::Done)->count();

        $this->status = match (true) {
            $done === $items->count() => TreatmentPlanStatus::Done,
            $done > 0 => TreatmentPlanStatus::PartiallyDone,
            default => $this->status,
        };

        $this->save();
    }

    protected function casts(): array
    {
        return [
            'status' => TreatmentPlanStatus::class,
            'presented_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
