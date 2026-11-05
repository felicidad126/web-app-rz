<?php

namespace App\Services\Billing;

use App\Enums\DepositTransactionType;
use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\DepositTransaction;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Deposit pasien: ledger dengan saldo berjalan. Baris pasien dikunci saat menulis agar saldo tidak balapan.
 */
final class DepositLedger
{
    public static function balance(Patient $patient): int
    {
        return $patient->depositBalance();
    }

    public static function topUp(Patient $patient, CashSession $session, int $amount, PaymentMethod $method, ?string $notes = null): DepositTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal top up harus lebih dari 0.');
        }

        if (! $method->isMoneyIn()) {
            throw new InvalidArgumentException('Top up deposit harus dengan uang (tunai, transfer, QRIS, kartu).');
        }

        CashSessions::ensureOpen($session);

        return self::append($patient, $session->branch_id, DepositTransactionType::TopUp, $amount, [
            'method' => $method,
            'cash_session_id' => $session->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Tambah satu baris ledger. $amount bertanda (+ menambah saldo, − mengurangi).
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function append(Patient $patient, int $branchId, DepositTransactionType $type, int $amount, array $attributes = []): DepositTransaction
    {
        return DB::transaction(function () use ($patient, $branchId, $type, $amount, $attributes): DepositTransaction {
            Patient::query()->whereKey($patient->id)->lockForUpdate()->first();

            $balance = (int) DepositTransaction::where('patient_id', $patient->id)->latest('id')->value('balance_after');
            $after = $balance + $amount;

            if ($after < 0) {
                throw new InvalidArgumentException('Saldo deposit tidak cukup (saldo Rp'.number_format($balance, 0, ',', '.').').');
            }

            return DepositTransaction::create([
                ...$attributes,
                'patient_id' => $patient->id,
                'branch_id' => $branchId,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $after,
            ]);
        });
    }
}
