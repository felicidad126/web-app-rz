<?php

namespace App\Models;

use App\Enums\Occlusion;
use App\Enums\Palate;
use App\Enums\TorusMandibularis;
use App\Enums\TorusPalatinus;
use App\Models\Concerns\LockedWithMedicalRecord;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use App\Services\Clinic\OralHygiene;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pemeriksaan intraoral & OHI-S satu kunjungan. Indeks dihitung ulang setiap disimpan (OralHygiene).
 *
 * @property int $id
 * @property int $visit_id
 * @property int $patient_id
 * @property Occlusion|null $occlusion
 * @property TorusPalatinus|null $torus_palatinus
 * @property TorusMandibularis|null $torus_mandibularis
 * @property Palate|null $palate
 * @property string|null $diastema
 * @property string|null $anomaly
 * @property array<string, array{debris?: int|null, calculus?: int|null}>|null $ohis_scores
 * @property string|null $debris_index
 * @property string|null $calculus_index
 * @property string|null $ohis
 * @property Carbon $created_at
 * @property-read Visit $visit
 */
#[Fillable(['visit_id', 'patient_id', 'occlusion', 'torus_palatinus', 'torus_mandibularis', 'palate', 'diastema', 'anomaly', 'ohis_scores'])]
class OralExamination extends Model
{
    use LockedWithMedicalRecord;
    use LogsAudit;
    use RecordsAuthor;

    protected bool $tracksUpdater = true;

    protected static function booted(): void
    {
        static::saving(function (OralExamination $exam): void {
            if (blank($exam->getAttribute('patient_id')) && filled($exam->getAttribute('visit_id'))) {
                $exam->patient_id = (int) Visit::query()->whereKey($exam->visit_id)->value('patient_id');
            }

            $result = OralHygiene::calculate($exam->ohis_scores);
            $exam->forceFill([
                'debris_index' => $result['debris_index'],
                'calculus_index' => $result['calculus_index'],
                'ohis' => $result['ohis'],
            ]);
        });
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function lockingVisit(): ?Visit
    {
        return $this->visit;
    }

    public function category(): ?string
    {
        return OralHygiene::category($this->ohis === null ? null : (float) $this->ohis);
    }

    protected function casts(): array
    {
        return [
            'occlusion' => Occlusion::class,
            'torus_palatinus' => TorusPalatinus::class,
            'torus_mandibularis' => TorusMandibularis::class,
            'palate' => Palate::class,
            'ohis_scores' => 'array',
            'debris_index' => 'decimal:2',
            'calculus_index' => 'decimal:2',
            'ohis' => 'decimal:2',
        ];
    }
}
