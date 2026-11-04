<?php

namespace App\Models;

use App\Enums\BloodType;
use App\Enums\Gender;
use App\Models\Concerns\HasEncryptedNik;
use App\Models\Concerns\LogsAudit;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $rm_number
 * @property string $name
 * @property string|null $nik
 * @property string|null $nik_hash
 * @property Carbon $birth_date
 * @property Gender $gender
 * @property BloodType|null $blood_type
 * @property Carbon|null $consent_data_at
 * @property int|null $created_by
 * @property-read int $age
 */
#[Fillable([
    'rm_number', 'name', 'nik', 'satusehat_ihs_id', 'birth_place', 'birth_date', 'gender', 'phone', 'email',
    'address', 'occupation', 'blood_type', 'allergies', 'systemic_diseases', 'emergency_contact_name',
    'emergency_contact_phone', 'emergency_contact_relation', 'default_payer_id', 'registered_branch_id',
    'consent_data_at', 'notes',
])]
class Patient extends Model
{
    use HasEncryptedNik;
    use HasFactory;
    use LogsAudit;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Patient $patient): void {
            if (blank($patient->rm_number)) {
                $patient->rm_number = NumberSequence::nextRmNumber();
            }

            if ($patient->created_by === null && auth()->check()) {
                $patient->created_by = auth()->id();
            }
        });
    }

    /** @return BelongsTo<Payer, $this> */
    public function defaultPayer(): BelongsTo
    {
        return $this->belongsTo(Payer::class, 'default_payer_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function registeredBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'registered_branch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PatientPayer, $this> */
    public function patientPayers(): HasMany
    {
        return $this->hasMany(PatientPayer::class);
    }

    protected function age(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->birth_date->age);
    }

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'birth_date' => 'date',
            'gender' => Gender::class,
            'blood_type' => BloodType::class,
            'consent_data_at' => 'datetime',
        ];
    }
}
