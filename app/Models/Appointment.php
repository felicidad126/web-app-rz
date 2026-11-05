<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\TreatmentCategory;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property int|null $chair_id
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property AppointmentStatus $status
 * @property TreatmentCategory|null $category
 * @property string|null $reason
 * @property string|null $notes
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 */
#[Fillable([
    'branch_id', 'patient_id', 'practitioner_id', 'chair_id', 'start_at', 'end_at', 'status', 'category',
    'reason', 'notes', 'confirmed_at', 'cancelled_at', 'cancel_reason',
])]
class Appointment extends Model
{
    use HasFactory;
    use LogsAudit;
    use RecordsAuthor;
    use SoftDeletes;

    protected bool $tracksUpdater = true;

    /** Status yang tetap memakai slot waktu (dihitung saat cek bentrok). */
    public const ACTIVE_STATUSES = [AppointmentStatus::Booked, AppointmentStatus::Confirmed, AppointmentStatus::Arrived];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

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

    /** @return BelongsTo<Chair, $this> */
    public function chair(): BelongsTo
    {
        return $this->belongsTo(Chair::class);
    }

    /** @return HasOne<Visit, $this> */
    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class);
    }

    /** @param Builder<Appointment> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /** @param Builder<Appointment> $query */
    public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): void
    {
        $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }

    public function canCheckIn(): bool
    {
        return in_array($this->status, [AppointmentStatus::Booked, AppointmentStatus::Confirmed], true)
            && $this->start_at->isToday();
    }

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'category' => TreatmentCategory::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
