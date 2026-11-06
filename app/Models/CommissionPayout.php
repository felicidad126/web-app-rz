<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Rekap jasa dokter per bulan: entri jasa yang sudah diakui + uang hadir/on call + tambahan/potongan manual
 * (mis. PPh 21 diisi bagian keuangan). Draf → final → dibayar.
 *
 * @property int $id
 * @property int $practitioner_id
 * @property Carbon $period
 * @property PayoutStatus $status
 * @property int $commission_total
 * @property int $allowance_total
 * @property int $adjustment_total
 * @property int $total
 * @property string|null $notes
 * @property int|null $finalized_by
 * @property Carbon|null $finalized_at
 * @property int|null $paid_by
 * @property Carbon|null $paid_at
 * @property-read Practitioner $practitioner
 */
#[Fillable(['practitioner_id', 'period', 'status', 'notes'])]
class CommissionPayout extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'commission_total' => 0, 'allowance_total' => 0, 'adjustment_total' => 0, 'total' => 0];

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return HasMany<Commission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class)->orderBy('service_date')->orderBy('id');
    }

    /** @return HasMany<PractitionerAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(PractitionerAttendance::class)->orderBy('date');
    }

    /** @return HasMany<CommissionPayoutAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(CommissionPayoutAdjustment::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool
    {
        return $this->status === PayoutStatus::Draft;
    }

    public function periodLabel(): string
    {
        return $this->period->locale('id')->translatedFormat('F Y');
    }

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'status' => PayoutStatus::class,
            'commission_total' => 'integer',
            'allowance_total' => 'integer',
            'adjustment_total' => 'integer',
            'total' => 'integer',
            'finalized_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
