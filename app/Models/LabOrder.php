<?php

namespace App\Models;

use App\Enums\LabOrderStatus;
use App\Enums\LabWorkType;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Order ke lab gigi (crown, gigi tiruan, retainer, …). Biaya lab dipakai untuk margin & basis jasa dokter (Fase 6).
 *
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $visit_procedure_id
 * @property int $practitioner_id
 * @property int $supplier_id
 * @property LabWorkType $work_type
 * @property string $specification
 * @property string|null $teeth
 * @property string|null $shade
 * @property int $cost
 * @property LabOrderStatus $status
 * @property Carbon|null $due_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $received_at
 * @property Carbon|null $checked_at
 * @property Carbon|null $fitted_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read Branch $branch
 * @property-read Patient $patient
 * @property-read Practitioner $practitioner
 * @property-read Supplier $supplier
 */
#[Fillable([
    'number', 'branch_id', 'patient_id', 'visit_id', 'visit_procedure_id', 'practitioner_id', 'supplier_id', 'work_type',
    'specification', 'teeth', 'shade', 'cost', 'status', 'due_at', 'notes',
])]
class LabOrder extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    protected bool $tracksUpdater = true;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'created', 'cost' => 0];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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

    /** @return BelongsTo<VisitProcedure, $this> */
    public function visitProcedure(): BelongsTo
    {
        return $this->belongsTo(VisitProcedure::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', LabOrderStatus::active());
    }

    /** Terlambat: belum diterima klinik padahal jatuh tempo sudah lewat. */
    public function isLate(): bool
    {
        return $this->due_at !== null && in_array($this->status, [LabOrderStatus::Created, LabOrderStatus::Sent], true)
            && $this->due_at->lt(today());
    }

    protected function casts(): array
    {
        return [
            'work_type' => LabWorkType::class,
            'status' => LabOrderStatus::class,
            'cost' => 'integer',
            'due_at' => 'date',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'checked_at' => 'datetime',
            'fitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
