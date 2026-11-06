<?php

namespace App\Services\Commission;

use App\Enums\CommissionStatus;
use App\Enums\PayoutStatus;
use App\Enums\SalaryComponentKind;
use App\Models\Commission;
use App\Models\CommissionPayout;
use App\Models\CommissionPayoutAdjustment;
use App\Models\PractitionerAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rekap jasa dokter per bulan. Rekap memuat semua entri jasa yang sudah diakui s.d. akhir bulan dan belum direkap
 * (termasuk susulan & entri pembalik), uang hadir/on call, serta tambahan/potongan manual.
 *
 * Draf bisa dibuat ulang kapan saja; final mengunci isinya; dibayar menandai entri jasa "Dibayar".
 */
final class CommissionPayouts
{
    /** @return Collection<int, CommissionPayout> rekap draf yang dibuat/diperbarui */
    public static function generate(Carbon $period, User $user, ?int $practitionerId = null): Collection
    {
        self::ensureCanManage($user);
        $start = $period->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Susulan: tagihan terbit yang entri jasanya belum tercatat (mis. aturan dibuat belakangan).
        CommissionEngine::recordMissing($start->copy()->subMonths(3), $end);

        $fromCommissions = Commission::query()->where('status', CommissionStatus::Earned)->whereNull('commission_payout_id')
            ->where('earned_at', '<=', $end->copy()->endOfDay())->distinct()->pluck('practitioner_id');
        $fromAttendance = PractitionerAttendance::query()->whereNull('commission_payout_id')->whereDate('date', '<=', $end)
            ->distinct()->pluck('practitioner_id');
        $existing = CommissionPayout::query()->whereDate('period', $start)->where('status', PayoutStatus::Draft)->pluck('practitioner_id');

        $practitioners = $fromCommissions->merge($fromAttendance)->merge($existing)->unique()
            ->when($practitionerId !== null, fn (Collection $ids) => $ids->filter(fn ($id): bool => (int) $id === $practitionerId));

        return $practitioners->map(fn ($id): ?CommissionPayout => DB::transaction(function () use ($id, $start, $end): ?CommissionPayout {
            $payout = CommissionPayout::query()->where('practitioner_id', $id)->whereDate('period', $start)->lockForUpdate()->first()
                ?? CommissionPayout::create(['practitioner_id' => $id, 'period' => $start->toDateString(), 'status' => PayoutStatus::Draft]);

            if (! $payout->isDraft()) {
                return null;
            }

            Commission::query()->where('commission_payout_id', $payout->id)->update(['commission_payout_id' => null]);
            PractitionerAttendance::query()->where('commission_payout_id', $payout->id)->update(['commission_payout_id' => null]);

            Commission::query()->where('practitioner_id', $id)->where('status', CommissionStatus::Earned)->whereNull('commission_payout_id')
                ->where('earned_at', '<=', $end->copy()->endOfDay())->update(['commission_payout_id' => $payout->id]);
            PractitionerAttendance::query()->where('practitioner_id', $id)->whereNull('commission_payout_id')
                ->whereDate('date', '<=', $end)->update(['commission_payout_id' => $payout->id]);

            self::recalculate($payout);

            return $payout;
        }))->filter()->values();
    }

    public static function recalculate(CommissionPayout $payout): void
    {
        $commission = (int) Commission::query()->where('commission_payout_id', $payout->id)->sum('amount');
        $allowance = (int) PractitionerAttendance::query()->where('commission_payout_id', $payout->id)->sum('amount');
        $adjustment = (int) $payout->adjustments()->get()->sum(fn (CommissionPayoutAdjustment $a): int => $a->signedAmount());

        $payout->forceFill([
            'commission_total' => $commission,
            'allowance_total' => $allowance,
            'adjustment_total' => $adjustment,
            'total' => $commission + $allowance + $adjustment,
        ])->save();
    }

    public static function recalculateById(int $payoutId): void
    {
        if ($payout = CommissionPayout::find($payoutId)) {
            self::recalculate($payout);
        }
    }

    public static function addAdjustment(CommissionPayout $payout, SalaryComponentKind $kind, string $description, int $amount, User $user): void
    {
        self::ensureCanManage($user);
        self::ensureDraft($payout);

        if ($amount <= 0 || trim($description) === '') {
            throw new InvalidArgumentException('Isi keterangan dan nominal lebih dari 0.');
        }

        $payout->adjustments()->create(['kind' => $kind, 'description' => trim($description), 'amount' => $amount]);
        self::recalculate($payout);
    }

    public static function removeAdjustment(CommissionPayoutAdjustment $adjustment, User $user): void
    {
        self::ensureCanManage($user);
        $payout = $adjustment->payout;
        self::ensureDraft($payout);

        $adjustment->delete();
        self::recalculate($payout);
    }

    public static function finalize(CommissionPayout $payout, User $user): void
    {
        self::ensureCanManage($user);
        self::ensureDraft($payout);

        $payout->forceFill(['status' => PayoutStatus::Final, 'finalized_by' => $user->id, 'finalized_at' => now()])->save();
    }

    public static function reopen(CommissionPayout $payout, User $user): void
    {
        self::ensureCanManage($user);

        if ($payout->status !== PayoutStatus::Final) {
            throw new InvalidArgumentException('Hanya rekap final (belum dibayar) yang bisa dibuka kembali.');
        }

        $payout->forceFill(['status' => PayoutStatus::Draft, 'finalized_by' => null, 'finalized_at' => null])->save();
    }

    public static function markPaid(CommissionPayout $payout, User $user): void
    {
        self::ensureCanManage($user);

        if ($payout->status !== PayoutStatus::Final) {
            throw new InvalidArgumentException('Finalkan rekap dulu sebelum ditandai dibayar.');
        }

        DB::transaction(function () use ($payout, $user): void {
            $payout->forceFill(['status' => PayoutStatus::Paid, 'paid_by' => $user->id, 'paid_at' => now()])->save();
            Commission::query()->where('commission_payout_id', $payout->id)->where('status', CommissionStatus::Earned)
                ->update(['status' => CommissionStatus::Paid]);
        });
    }

    private static function ensureDraft(CommissionPayout $payout): void
    {
        if (! $payout->isDraft()) {
            throw new InvalidArgumentException('Rekap '.$payout->periodLabel().' sudah '.$payout->status->getLabel().'. Buka kembali dulu untuk mengubah.');
        }
    }

    private static function ensureCanManage(User $user): void
    {
        if (! $user->can('Manage:Commission')) {
            throw new InvalidArgumentException('Hanya owner yang bisa mengelola rekap jasa.');
        }
    }
}
