<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override tarif tindakan untuk cabang tertentu.
 *
 * @property int $price
 */
#[Fillable(['treatment_id', 'branch_id', 'price'])]
class TreatmentBranchPrice extends Model
{
    use LogsAudit;

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }
}
