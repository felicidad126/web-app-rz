<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jadwal praktik rutin dokter per hari.
 *
 * @property int $id
 * @property int $practitioner_id
 * @property int $branch_id
 * @property int|null $chair_id
 * @property int $day_of_week 1 = Senin ... 7 = Minggu
 * @property string $start_time
 * @property string $end_time
 * @property bool $is_active
 */
#[Fillable(['practitioner_id', 'branch_id', 'chair_id', 'day_of_week', 'start_time', 'end_time', 'is_active'])]
class PractitionerSchedule extends Model
{
    use LogsAudit;
    use SoftDeletes;

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Chair, $this> */
    public function chair(): BelongsTo
    {
        return $this->belongsTo(Chair::class);
    }

    /** @param Builder<PractitionerSchedule> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function dayName(): string
    {
        return self::DAYS[$this->day_of_week] ?? (string) $this->day_of_week;
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
