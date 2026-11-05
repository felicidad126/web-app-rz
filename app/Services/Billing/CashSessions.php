<?php

namespace App\Services\Billing;

use App\Enums\CashDifferenceReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\CashSession;
use App\Models\DepositTransaction;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sesi kasir: satu sesi terbuka per kasir per cabang. Semua uang masuk/keluar tercatat ke sesi yang terbuka.
 */
final class CashSessions
{
    public static function current(User $user, ?int $branchId): ?CashSession
    {
        if ($branchId === null) {
            return null;
        }

        return CashSession::query()->open()->where('user_id', $user->id)->where('branch_id', $branchId)->latest('id')->first();
    }

    public static function open(User $user, int $branchId, ?int $openingCash = null): CashSession
    {
        if (self::current($user, $branchId) !== null) {
            throw new InvalidArgumentException('Anda masih punya sesi kasir yang terbuka di cabang ini.');
        }

        return CashSession::create([
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => $openingCash ?? self::suggestedOpeningCash($branchId),
        ]);
    }

    /** Kas awal = "kas awal besok" dari sesi terakhir yang ditutup di cabang ini. */
    public static function suggestedOpeningCash(int $branchId): int
    {
        return (int) CashSession::query()->where('branch_id', $branchId)->whereNotNull('closed_at')
            ->latest('closed_at')->value('next_opening_cash');
    }

    public static function ensureOpen(CashSession $session): void
    {
        if (! $session->isOpen()) {
            throw new InvalidArgumentException('Sesi kasir sudah ditutup. Buka sesi baru dulu.');
        }
    }

    /**
     * Uang bersih per metode menurut sistem: pembayaran aktif + top up deposit − refund yang diserahkan.
     * Pembayaran yang masih menunggu persetujuan void tetap dihitung sampai disetujui.
     *
     * @return array<string, int> [method => jumlah], hanya metode uang (bukan deposit)
     */
    public static function systemTotals(CashSession $session): array
    {
        $totals = array_fill_keys(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::moneyIn()), 0);

        $payments = Payment::query()
            ->whereHas('transaction', fn ($q) => $q->where('cash_session_id', $session->id))
            ->where('status', '!=', PaymentStatus::Void)
            ->selectRaw('method, sum(amount) as total')->groupBy('method')->pluck('total', 'method');

        $topUps = DepositTransaction::query()->where('cash_session_id', $session->id)->where('amount', '>', 0)->whereNotNull('method')
            ->selectRaw('method, sum(amount) as total')->groupBy('method')->pluck('total', 'method');

        $refunds = Refund::query()->where('cash_session_id', $session->id)->where('status', RefundStatus::Paid)
            ->selectRaw('method, sum(amount) as total')->groupBy('method')->pluck('total', 'method');

        foreach (array_keys($totals) as $method) {
            $totals[$method] = (int) ($payments[$method] ?? 0) + (int) ($topUps[$method] ?? 0) - (int) ($refunds[$method] ?? 0);
        }

        return $totals;
    }

    public static function expectedCash(CashSession $session): int
    {
        return $session->opening_cash + self::systemTotals($session)[PaymentMethod::Cash->value];
    }

    /** @param array<int|string, int|string|null> $denominations [pecahan => lembar], kunci "coins" = koin/sisa dalam rupiah */
    public static function countDenominations(array $denominations): int
    {
        $total = 0;

        foreach ($denominations as $value => $count) {
            $total += $value === 'coins' ? (int) $count : (int) $value * (int) $count;
        }

        return $total;
    }

    /**
     * @param  array{
     *     denominations: array<int|string, int|string|null>,
     *     reported?: array<string, int|string|null>,
     *     difference_reason?: CashDifferenceReason|string|null,
     *     notes?: string|null,
     *     next_opening_cash?: int|null
     * }  $data
     */
    public static function close(CashSession $session, User $user, array $data): CashSession
    {
        self::ensureOpen($session);

        return DB::transaction(function () use ($session, $user, $data): CashSession {
            $system = self::systemTotals($session);
            $expected = $session->opening_cash + $system[PaymentMethod::Cash->value];
            $counted = self::countDenominations($data['denominations']);
            $difference = $counted - $expected;
            $methods = [];
            $mismatch = false;

            foreach ($system as $method => $amount) {
                if ($method === PaymentMethod::Cash->value) {
                    $methods[$method] = ['system' => $expected, 'reported' => $counted, 'matched' => $difference === 0];

                    continue;
                }

                $reported = $data['reported'][$method] ?? null;
                $reported = $reported === null || $reported === '' ? null : (int) $reported;

                if ($amount !== 0 && $reported === null) {
                    throw new InvalidArgumentException('Isi hasil laporan '.PaymentMethod::from($method)->getLabel().' ('.PaymentMethod::from($method)->reconciliationSource().').');
                }

                $matched = ($reported ?? 0) === $amount;
                $mismatch = $mismatch || ! $matched;
                $methods[$method] = ['system' => $amount, 'reported' => $reported, 'matched' => $matched];
            }

            $reason = $data['difference_reason'] ?? null;
            $reason = is_string($reason) && $reason !== '' ? CashDifferenceReason::from($reason) : ($reason instanceof CashDifferenceReason ? $reason : null);

            if (($difference !== 0 || $mismatch) && ($reason === null || blank($data['notes'] ?? null) && $reason === CashDifferenceReason::Other)) {
                throw new InvalidArgumentException('Ada selisih. Pilih penyebab selisih (dan isi catatan bila "Lainnya").');
            }

            $nextOpening = (int) ($data['next_opening_cash'] ?? $session->opening_cash);

            if ($nextOpening > $counted) {
                throw new InvalidArgumentException('Kas awal besok tidak boleh lebih besar dari uang yang dihitung.');
            }

            $session->forceFill([
                'closed_at' => now(),
                'closed_by' => $user->id,
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'difference' => $difference,
                'denominations' => $data['denominations'],
                'method_totals' => $methods,
                'difference_reason' => $difference !== 0 || $mismatch ? $reason : null,
                'notes' => $data['notes'] ?? null,
                'next_opening_cash' => $nextOpening,
                'deposited_to_safe' => $counted - $nextOpening,
            ])->save();

            return $session;
        });
    }
}
