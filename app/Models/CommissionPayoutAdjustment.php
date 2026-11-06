<?php

namespace App\Models;

use App\Enums\SalaryComponentKind;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tambahan atau potongan manual di rekap jasa (mis. PPh 21, kasbon, insentif).
 *
 * @property int $id
 * @property int $commission_payout_id
 * @property SalaryComponentKind $kind
 * @property string $description
 * @property int $amount
 */
#[Fillable(['commission_payout_id', 'kind', 'description', 'amount'])]
class CommissionPayoutAdjustment extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    /** @return BelongsTo<CommissionPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(CommissionPayout::class, 'commission_payout_id');
    }

    public function signedAmount(): int
    {
        return $this->kind === SalaryComponentKind::Deduction ? -$this->amount : $this->amount;
    }

    protected function casts(): array
    {
        return ['kind' => SalaryComponentKind::class, 'amount' => 'integer'];
    }
}
