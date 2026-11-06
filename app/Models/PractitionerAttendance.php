<?php

namespace App\Models;

use App\Enums\AttendanceType;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kehadiran dokter yang dibayar (uang hadir / on call). Nominal di-snapshot dari tarif dokter saat dicatat.
 *
 * @property int $id
 * @property int $practitioner_id
 * @property int $branch_id
 * @property Carbon $date
 * @property AttendanceType $type
 * @property int $amount
 * @property string|null $notes
 * @property int|null $commission_payout_id
 * @property-read Practitioner $practitioner
 * @property-read Branch $branch
 */
#[Fillable(['practitioner_id', 'branch_id', 'date', 'type', 'amount', 'notes'])]
class PractitionerAttendance extends Model
{
    use LogsAudit;
    use RecordsAuthor;

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

    /** @return BelongsTo<CommissionPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(CommissionPayout::class, 'commission_payout_id');
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'type' => AttendanceType::class, 'amount' => 'integer'];
    }
}
