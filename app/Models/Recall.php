<?php

namespace App\Models;

use App\Enums\RecallStatus;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Pengingat kontrol berikutnya (scaling 6 bulan, kontrol ortho, dst.).
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $branch_id
 * @property int|null $treatment_id
 * @property int|null $visit_id
 * @property int|null $appointment_id
 * @property string $reason
 * @property Carbon $due_date
 * @property RecallStatus $status
 * @property Carbon|null $last_contacted_at
 * @property string|null $notes
 */
#[Fillable(['patient_id', 'branch_id', 'treatment_id', 'visit_id', 'appointment_id', 'reason', 'due_date', 'status', 'last_contacted_at', 'notes'])]
class Recall extends Model
{
    use LogsAudit;
    use RecordsAuthor;
    use SoftDeletes;

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [RecallStatus::Pending, RecallStatus::Contacted], true) && $this->due_date->isPast();
    }

    protected function casts(): array
    {
        return [
            'status' => RecallStatus::class,
            'due_date' => 'date',
            'last_contacted_at' => 'datetime',
        ];
    }
}
