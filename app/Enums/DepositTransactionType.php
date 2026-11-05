<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis mutasi deposit pasien. */
enum DepositTransactionType: string implements HasColor, HasLabel
{
    case TopUp = 'topup';
    case Usage = 'usage';
    case Reversal = 'reversal';
    case RefundCredit = 'refund_credit';
    case Withdrawal = 'withdrawal';

    public function getLabel(): string
    {
        return match ($this) {
            self::TopUp => 'Top up',
            self::Usage => 'Dipakai bayar',
            self::Reversal => 'Pembatalan pemakaian',
            self::RefundCredit => 'Refund ke deposit',
            self::Withdrawal => 'Dikembalikan ke pasien',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TopUp => 'success',
            self::Usage => 'gray',
            self::Reversal => 'info',
            self::RefundCredit => 'info',
            self::Withdrawal => 'warning',
        };
    }
}
