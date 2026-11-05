<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu kali "Terima pembayaran" (satu struk). Bisa melunasi beberapa tagihan pasien sekaligus.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $branch_id
 * @property int $cash_session_id
 * @property int|null $received_by
 * @property Carbon $paid_at
 * @property int $total
 * @property int|null $cash_tendered
 * @property int|null $change_given
 */
#[Fillable(['patient_id', 'branch_id', 'cash_session_id', 'received_by', 'paid_at', 'total', 'cash_tendered', 'change_given'])]
class PaymentTransaction extends Model
{
    use LogsAudit;

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

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'total' => 'integer',
            'cash_tendered' => 'integer',
            'change_given' => 'integer',
        ];
    }
}
