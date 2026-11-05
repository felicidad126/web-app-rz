<?php

namespace App\Models;

use App\Enums\PractitionerType;
use App\Models\Concerns\HasEncryptedNik;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Profil tenaga kesehatan (dokter gigi, terapis, perawat).
 *
 * @property int $id
 * @property int $user_id
 * @property PractitionerType $type
 * @property string|null $nik
 * @property string|null $nik_hash
 * @property Carbon|null $sip_expired_at
 */
#[Fillable(['user_id', 'type', 'specialty', 'nik', 'str_number', 'sip_number', 'sip_expired_at', 'satusehat_ihs_id'])]
class Practitioner extends Model
{
    use HasEncryptedNik;
    use HasFactory;
    use LogsAudit;
    use SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * SIP yang sudah kedaluwarsa atau akan kedaluwarsa dalam N hari.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    /** @return HasMany<PractitionerSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(PractitionerSchedule::class);
    }

    /** @return HasMany<ScheduleException, $this> */
    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function displayName(): string
    {
        return $this->user->name ?? ('Nakes #'.$this->id);
    }

    public function scopeSipExpiringWithin(Builder $query, int $days): Builder
    {
        return self::applySipExpiringWithin($query, $days);
    }

    /**
     * @template TBuilder of Builder<Practitioner>
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function applySipExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->whereNotNull('sip_expired_at')
            ->whereDate('sip_expired_at', '<=', now()->addDays($days));
    }

    public function isSipExpired(): bool
    {
        return $this->sip_expired_at !== null && $this->sip_expired_at->isPast();
    }

    protected function casts(): array
    {
        return [
            'type' => PractitionerType::class,
            'nik' => 'encrypted',
            'sip_expired_at' => 'date',
        ];
    }
}
