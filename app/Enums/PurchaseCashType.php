<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Jenis entri kas pembelian. */
enum PurchaseCashType: string implements HasLabel
{
    case Deposit = 'deposit';
    case Payment = 'payment';
    case Expense = 'expense';
    case Refund = 'refund';
    case Correction = 'correction';

    public function getLabel(): string
    {
        return match ($this) {
            self::Deposit => 'Setoran kas',
            self::Payment => 'Pembayaran pesanan',
            self::Expense => 'Pengeluaran lain',
            self::Refund => 'Pengembalian pesanan batal',
            self::Correction => 'Koreksi',
        };
    }
}
