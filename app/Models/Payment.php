<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pembayaran untuk satu tagihan. Tidak pernah dihapus: salah input dibatalkan lewat void + persetujuan owner.
 *
 * @property int $id
 * @property int $payment_transaction_id
 * @property int $invoice_id
 * @property PaymentMethod $method
 * @property int $amount
 * @property string|null $reference_no
 * @property PaymentStatus $status
 * @property string|null $void_reason
 * @property int|null $void_requested_by
 * @property Carbon|null $void_requested_at
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property Carbon $created_at
 * @property-read PaymentTransaction $transaction
 */
#[Fillable(['payment_transaction_id', 'invoice_id', 'method', 'amount', 'reference_no'])]
class Payment extends Model
{
    use LogsAudit;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active'];

    /** @return BelongsTo<PaymentTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function voidRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'void_requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'void_requested_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
