<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Cuti/libur dokter. Jam kosong = tidak praktik sepanjang hari.
 *
 * @property int $id
 * @property int $practitioner_id
 * @property Carbon $date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string $reason
 */
#[Fillable(['practitioner_id', 'date', 'start_time', 'end_time', 'reason'])]
class ScheduleException extends Model
{
    use LogsAudit;
    use RecordsAuthor;
    use SoftDeletes;

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function isWholeDay(): bool
    {
        return blank($this->start_time) || blank($this->end_time);
    }

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
