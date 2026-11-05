<?php

namespace App\Models;

use App\Exceptions\MedicalRecordLockedException;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Rekam medis (SOAP) satu kunjungan. Setelah ditandatangani dokter, isinya terkunci.
 *
 * @property int $id
 * @property int $visit_id
 * @property int $patient_id
 * @property string|null $subjective
 * @property string|null $objective
 * @property array<string, mixed>|null $vitals
 * @property string|null $assessment
 * @property string|null $plan
 * @property int|null $signed_by
 * @property Carbon|null $signed_at
 */
#[Fillable(['visit_id', 'patient_id', 'subjective', 'objective', 'vitals', 'assessment', 'plan'])]
class MedicalRecord extends Model
{
    use LogsAudit;
    use RecordsAuthor;
    use SoftDeletes;

    protected bool $tracksUpdater = true;

    protected static function booted(): void
    {
        static::updating(function (MedicalRecord $record): void {
            // Penandatanganan mengubah signed_at dari null; setelah itu tidak ada perubahan lagi.
            if ($record->getOriginal('signed_at') !== null) {
                throw new MedicalRecordLockedException;
            }
        });

        static::deleting(function (MedicalRecord $record): void {
            if ($record->signed_at !== null) {
                throw new MedicalRecordLockedException;
            }
        });
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    /** @return HasMany<Diagnosis, $this> */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    /** @return HasMany<MedicalRecordAddendum, $this> */
    public function addenda(): HasMany
    {
        return $this->hasMany(MedicalRecordAddendum::class)->latest();
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    protected function casts(): array
    {
        return [
            'vitals' => 'array',
            'signed_at' => 'datetime',
        ];
    }
}
