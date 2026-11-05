<?php

namespace App\Models;

use App\Enums\DiagnosisType;
use App\Models\Concerns\LockedWithMedicalRecord;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $medical_record_id
 * @property string $icd10_code
 * @property DiagnosisType $type
 * @property string|null $tooth_number
 * @property string|null $notes
 */
#[Fillable(['medical_record_id', 'icd10_code', 'type', 'tooth_number', 'notes'])]
class Diagnosis extends Model
{
    use LockedWithMedicalRecord;
    use LogsAudit;
    use SoftDeletes;

    /** @return BelongsTo<MedicalRecord, $this> */
    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    /** @return BelongsTo<Icd10Code, $this> */
    public function icd10(): BelongsTo
    {
        return $this->belongsTo(Icd10Code::class, 'icd10_code', 'code');
    }

    public function lockingVisit(): ?Visit
    {
        return $this->medicalRecord?->visit;
    }

    protected function casts(): array
    {
        return ['type' => DiagnosisType::class];
    }
}
