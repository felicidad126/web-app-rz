<?php

namespace App\Models;

use App\Enums\TreatmentCategory;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master tindakan / layanan beserta tarif.
 *
 * @property int $id
 * @property TreatmentCategory $category
 * @property int $base_price
 * @property int $duration_minutes
 */
#[Fillable(['code', 'name', 'category', 'icd9cm_code', 'base_price', 'duration_minutes', 'recall_months', 'description', 'is_active'])]
class Treatment extends Model
{
    use HasFactory;
    use LogsAudit;
    use SoftDeletes;

    /** @return BelongsTo<Icd9cmCode, $this> */
    public function icd9cm(): BelongsTo
    {
        return $this->belongsTo(Icd9cmCode::class, 'icd9cm_code', 'code');
    }

    /** @return HasMany<TreatmentBranchPrice, $this> */
    public function branchPrices(): HasMany
    {
        return $this->hasMany(TreatmentBranchPrice::class);
    }

    /** Tarif yang berlaku di cabang tertentu (override cabang, atau tarif dasar). */
    public function priceForBranch(?int $branchId): int
    {
        if ($branchId === null) {
            return $this->base_price;
        }

        $override = $this->relationLoaded('branchPrices')
            ? $this->branchPrices->firstWhere('branch_id', $branchId)
            : $this->branchPrices()->where('branch_id', $branchId)->first();

        return $override->price ?? $this->base_price;
    }

    protected function casts(): array
    {
        return [
            'category' => TreatmentCategory::class,
            'base_price' => 'integer',
            'duration_minutes' => 'integer',
            'recall_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
