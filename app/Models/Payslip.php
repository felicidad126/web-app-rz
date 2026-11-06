<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Enums\SalaryComponentKind;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Slip gaji bulanan staf. Barisnya bisa diubah selama draf; setelah final hanya bisa ditandai dibayar.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $period
 * @property PayoutStatus $status
 * @property int $earning_total
 * @property int $deduction_total
 * @property int $net_total
 * @property string|null $notes
 * @property Carbon|null $finalized_at
 * @property Carbon|null $paid_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'period', 'status', 'notes'])]
class Payslip extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'earning_total' => 0, 'deduction_total' => 0, 'net_total' => 0];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Slip gaji tidak bisa dihapus.'));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PayslipLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->orderBy('sort')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === PayoutStatus::Draft;
    }

    public function periodLabel(): string
    {
        return $this->period->locale('id')->translatedFormat('F Y');
    }

    /** Hitung ulang total dari baris. */
    public function recalculate(): void
    {
        $lines = $this->lines()->get();
        $earning = (int) $lines->where('kind', SalaryComponentKind::Earning)->sum('amount');
        $deduction = (int) $lines->where('kind', SalaryComponentKind::Deduction)->sum('amount');

        $this->forceFill(['earning_total' => $earning, 'deduction_total' => $deduction, 'net_total' => $earning - $deduction])->save();
    }

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'status' => PayoutStatus::class,
            'earning_total' => 'integer',
            'deduction_total' => 'integer',
            'net_total' => 'integer',
            'finalized_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
