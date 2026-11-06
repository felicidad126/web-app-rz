<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status entri jasa. */
enum CommissionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Earned = 'earned';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu lunas',
            self::Earned => 'Diakui',
            self::Paid => 'Dibayar',
            self::Cancelled => 'Batal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Earned => 'gray',
            self::Paid => 'success',
            self::Cancelled => 'gray',
        };
    }
}
