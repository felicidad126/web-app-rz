<?php

namespace App\Models;

use App\Enums\ToothSurface;
use App\Models\Concerns\LockedWithMedicalRecord;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tindakan yang dikerjakan pada kunjungan. Tarif di-snapshot saat dibuat.
 *
 * @property int $id
 * @property int $visit_id
 * @property int $treatment_id
 * @property string|null $icd9cm_code
 * @property string|null $tooth_number
 * @property string|null $surfaces
 * @property int $operator_id
 * @property int|null $assistant_id
 * @property int $qty
 * @property int $price_snapshot
 * @property int|null $treatment_plan_item_id
 * @property string|null $notes
 */
#[Fillable([
    'visit_id', 'treatment_id', 'icd9cm_code', 'tooth_number', 'surfaces', 'operator_id', 'assistant_id',
    'qty', 'price_snapshot', 'treatment_plan_item_id', 'notes',
])]
class VisitProcedure extends Model
{
    use LockedWithMedicalRecord;
    use LogsAudit;
    use RecordsAuthor;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (VisitProcedure $procedure): void {
            $treatment = $procedure->treatment;

            // Snapshot kode & tarif tindakan saat dikerjakan (perubahan master tidak mengubah riwayat).
            $procedure->icd9cm_code ??= $treatment?->icd9cm_code;
            $procedure->price_snapshot ??= $treatment?->priceForBranch($procedure->visit?->branch_id) ?? 0;
            $procedure->qty ??= 1;
        });

        static::saving(function (VisitProcedure $procedure): void {
            $procedure->surfaces = ToothSurface::normalize($procedure->surfaces);
        });
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class)->withTrashed();
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'operator_id');
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'assistant_id');
    }

    /** @return BelongsTo<TreatmentPlanItem, $this> */
    public function planItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class, 'treatment_plan_item_id');
    }

    public function lockingVisit(): ?Visit
    {
        return $this->visit;
    }

    public function total(): int
    {
        return $this->price_snapshot * $this->qty;
    }

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price_snapshot' => 'integer',
        ];
    }
}
