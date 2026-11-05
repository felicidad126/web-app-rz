<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Asal dana yang dikembalikan. */
enum RefundSource: string implements HasLabel
{
    case Invoice = 'invoice';
    case Deposit = 'deposit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Invoice => 'Pembayaran tagihan',
            self::Deposit => 'Saldo deposit',
        };
    }
}
