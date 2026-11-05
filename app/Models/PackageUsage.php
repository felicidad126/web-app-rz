<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pemakaian kuota paket pada satu kunjungan. Dibatalkan dengan voided_at (tagihan di-void), tidak dihapus.
 *
 * @property int $id
 * @property int $patient_package_id
 * @property int $treatment_id
 * @property int|null $visit_id
 * @property int|null $invoice_item_id
 * @property Carbon $used_at
 * @property Carbon|null $voided_at
 */
#[Fillable(['patient_package_id', 'treatment_id', 'visit_id', 'invoice_item_id', 'used_at', 'voided_at'])]
class PackageUsage extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    /** @return BelongsTo<PatientPackage, $this> */
    public function patientPackage(): BelongsTo
    {
        return $this->belongsTo(PatientPackage::class);
    }

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class)->withTrashed();
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
