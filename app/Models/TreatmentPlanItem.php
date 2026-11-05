<?php

namespace App\Models;

use App\Enums\ToothSurface;
use App\Enums\TreatmentPlanItemStatus;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $treatment_plan_id
 * @property int $treatment_id
 * @property string|null $tooth_number
 * @property string|null $surfaces
 * @property int $phase
 * @property int $sequence
 * @property int $price_estimate
 * @property TreatmentPlanItemStatus $status
 * @property string|null $notes
 */
#[Fillable(['treatment_plan_id', 'treatment_id', 'tooth_number', 'surfaces', 'phase', 'sequence', 'price_estimate', 'status', 'notes'])]
class TreatmentPlanItem extends Model
{
    use LogsAudit;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (TreatmentPlanItem $item): void {
            $item->surfaces = ToothSurface::normalize($item->surfaces);
        });
    }

    /** @return BelongsTo<TreatmentPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class)->withTrashed();
    }

    /** @return HasMany<VisitProcedure, $this> */
    public function procedures(): HasMany
    {
        return $this->hasMany(VisitProcedure::class);
    }

    public function label(): string
    {
        $tooth = $this->tooth_number ? ' '.$this->tooth_number.($this->surfaces ? ' '.$this->surfaces : '') : '';

        return 'Fase '.$this->phase.' · '.$this->treatment->name.$tooth;
    }

    protected function casts(): array
    {
        return [
            'phase' => 'integer',
            'sequence' => 'integer',
            'price_estimate' => 'integer',
            'status' => TreatmentPlanItemStatus::class,
        ];
    }
}
