<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status cicilan paket. */
enum InstallmentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Billed = 'billed';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Belum ditagih',
            self::Billed => 'Sudah ditagih',
            self::Paid => 'Lunas',
            self::Cancelled => 'Batal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Billed => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }
}
