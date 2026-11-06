<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komponen gaji tetap seorang karyawan (dipakai saat slip bulanan dibuat).
 *
 * @property int $id
 * @property int $user_id
 * @property int $salary_component_id
 * @property int $amount
 * @property-read SalaryComponent $component
 */
#[Fillable(['user_id', 'salary_component_id', 'amount'])]
class EmployeeSalaryItem extends Model
{
    use LogsAudit;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SalaryComponent, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id')->withTrashed();
    }

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
