<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use App\Enums\PatientPackageStatus;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Paket yang dibeli pasien: kuota tindakan + jadwal cicilan (DP = cicilan ke-0).
 *
 * @property int $id
 * @property string $package_number
 * @property int $patient_id
 * @property int $package_id
 * @property int $branch_id
 * @property int|null $practitioner_id
 * @property string $name
 * @property int $price
 * @property int $down_payment
 * @property int $installment_count
 * @property Carbon $start_date
 * @property int $due_day
 * @property Carbon|null $expires_at
 * @property PatientPackageStatus $status
 * @property string|null $notes
 * @property-read Patient $patient
 */
#[Fillable([
    'package_number', 'patient_id', 'package_id', 'branch_id', 'practitioner_id', 'name', 'price', 'down_payment',
    'installment_count', 'start_date', 'due_day', 'expires_at', 'status', 'notes',
])]
class PatientPackage extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    protected bool $tracksUpdater = true;

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Package, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class)->withTrashed();
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return HasMany<PatientPackageItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PatientPackageItem::class);
    }

    /** @return HasMany<Installment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('sequence');
    }

    /** @return HasMany<PackageUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(PackageUsage::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', PatientPackageStatus::Active)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()));
    }

    /** Sudah dibayar (cicilan lunas). Memakai relasi yang sudah dimuat bila ada. */
    public function paidAmount(): int
    {
        return (int) $this->installments->where('status', InstallmentStatus::Paid)->sum('amount');
    }

    public function remainingAmount(): int
    {
        return max(0, $this->price - $this->paidAmount());
    }

    /** Sisa kuota per tindakan: [treatment_id => sisa]. */
    public function remainingQuota(): array
    {
        $used = $this->usages()->active()->selectRaw('treatment_id, count(*) as total')->groupBy('treatment_id')->pluck('total', 'treatment_id');

        return $this->items()->pluck('quota', 'treatment_id')
            ->map(fn (int $quota, int $treatmentId): int => max(0, $quota - (int) ($used[$treatmentId] ?? 0)))
            ->all();
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'down_payment' => 'integer',
            'installment_count' => 'integer',
            'start_date' => 'date',
            'due_day' => 'integer',
            'expires_at' => 'date',
            'status' => PatientPackageStatus::class,
        ];
    }
}
