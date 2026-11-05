<?php

namespace App\Models;

use App\Enums\OdontogramEntryType;
use App\Enums\ToothSurface;
use App\Models\Concerns\LockedWithMedicalRecord;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu catatan odontogram: gigi + permukaan (null = seluruh gigi) + kode kondisi.
 * Riwayat tidak pernah ditimpa; status terkini = entri terakhir per gigi/permukaan.
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property string $tooth_number
 * @property ToothSurface|null $surface
 * @property string $condition_code
 * @property OdontogramEntryType $entry_type
 * @property string|null $notes
 */
#[Fillable(['patient_id', 'visit_id', 'tooth_number', 'surface', 'condition_code', 'entry_type', 'notes'])]
class OdontogramEntry extends Model
{
    use LockedWithMedicalRecord;
    use LogsAudit;
    use RecordsAuthor;
    use SoftDeletes;

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<OdontogramCondition, $this> */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(OdontogramCondition::class, 'condition_code', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lockingVisit(): ?Visit
    {
        return $this->visit;
    }

    protected function casts(): array
    {
        return [
            'surface' => ToothSurface::class,
            'entry_type' => OdontogramEntryType::class,
        ];
    }
}
