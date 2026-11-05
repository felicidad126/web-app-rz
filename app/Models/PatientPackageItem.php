<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kuota tindakan paket milik pasien (snapshot saat paket dijual).
 *
 * @property int $id
 * @property int $patient_package_id
 * @property int $treatment_id
 * @property int $quota
 */
#[Fillable(['patient_package_id', 'treatment_id', 'quota'])]
class PatientPackageItem extends Model
{
    /** @return BelongsTo<PatientPackage, $this> */
    public function patientPackage(): BelongsTo
    {
        return $this->belongsTo(PatientPackage::class);
    }

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class)->withTrashed();
    }

    protected function casts(): array
    {
        return ['quota' => 'integer'];
    }
}
