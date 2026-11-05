<?php

namespace App\Models;

use App\Enums\DepositTransactionType;
use App\Enums\PaymentMethod;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Mutasi deposit pasien (ledger, saldo berjalan). Hanya ditambah, tidak pernah diubah atau dihapus.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $branch_id
 * @property DepositTransactionType $type
 * @property int $amount
 * @property int $balance_after
 * @property PaymentMethod|null $method
 * @property int|null $cash_session_id
 * @property int|null $payment_id
 * @property int|null $refund_id
 * @property string|null $notes
 * @property int|null $created_by
 */
#[Fillable(['patient_id', 'branch_id', 'type', 'amount', 'balance_after', 'method', 'cash_session_id', 'payment_id', 'refund_id', 'notes'])]
class DepositTransaction extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Mutasi deposit tidak bisa diubah.'));
        static::deleting(fn () => throw new LogicException('Mutasi deposit tidak bisa dihapus.'));
    }

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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'type' => DepositTransactionType::class,
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }
}
