<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pengembalian dana ke pasien: diajukan kasir → disetujui owner → diserahkan kasir (masuk sesi kasir).
 *
 * @property int $id
 * @property int $branch_id
 * @property int $patient_id
 * @property int|null $invoice_id
 * @property RefundSource $source
 * @property int $amount
 * @property PaymentMethod $method
 * @property string $reason
 * @property RefundStatus $status
 * @property int|null $requested_by
 * @property Carbon $requested_at
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property int|null $paid_by
 * @property Carbon|null $paid_at
 * @property int|null $cash_session_id
 */
#[Fillable(['branch_id', 'patient_id', 'invoice_id', 'source', 'amount', 'method', 'reason', 'requested_by', 'requested_at'])]
class Refund extends Model
{
    use LogsAudit;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

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

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected function casts(): array
    {
        return [
            'source' => RefundSource::class,
            'method' => PaymentMethod::class,
            'status' => RefundStatus::class,
            'amount' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
