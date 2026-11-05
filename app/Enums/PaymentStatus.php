<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status baris pembayaran. */
enum PaymentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case VoidRequested = 'void_requested';
    case Void = 'void';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::VoidRequested => 'Menunggu persetujuan void',
            self::Void => 'Void',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::VoidRequested => 'warning',
            self::Void => 'danger',
        };
    }
}
