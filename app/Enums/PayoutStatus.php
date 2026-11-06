<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status rekap jasa dokter / slip gaji. */
enum PayoutStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Final = 'final';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Final => 'Final',
            self::Paid => 'Dibayar',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Final => 'info',
            self::Paid => 'success',
        };
    }
}
