<?php

namespace App\Models;

use App\Enums\CashDifferenceReason;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * Sesi kasir: dibuka dengan kas awal, ditutup dengan hitung fisik dan pencocokan per metode bayar.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $user_id
 * @property Carbon $opened_at
 * @property int $opening_cash
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property int|null $expected_cash
 * @property int|null $counted_cash
 * @property int|null $difference
 * @property array<string, int>|null $denominations
 * @property array<string, array{system: int, reported: int|null, matched: bool}>|null $method_totals
 * @property CashDifferenceReason|null $difference_reason
 * @property string|null $notes
 * @property int|null $deposited_to_safe
 * @property int|null $next_opening_cash
 * @property-read User $user
 * @property-read Branch $branch
 */
#[Fillable(['branch_id', 'user_id', 'opened_at', 'opening_cash'])]
class CashSession extends Model
{
    use LogsAudit;

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<PaymentTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /** @return HasManyThrough<Payment, PaymentTransaction, $this> */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, PaymentTransaction::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return HasMany<DepositTransaction, $this> */
    public function depositTransactions(): HasMany
    {
        return $this->hasMany(DepositTransaction::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function label(): string
    {
        return 'Sesi '.$this->user->name.' · '.$this->opened_at->translatedFormat('d M Y H:i');
    }

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'integer',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
            'difference' => 'integer',
            'denominations' => 'array',
            'method_totals' => 'array',
            'difference_reason' => CashDifferenceReason::class,
            'deposited_to_safe' => 'integer',
            'next_opening_cash' => 'integer',
        ];
    }
}
