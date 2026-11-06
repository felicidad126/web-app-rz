<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status stok opname. */
enum StockOpnameStatus: string implements HasColor, HasLabel
{
    case Counting = 'counting';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Counting => 'Sedang dihitung',
            self::Pending => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Counting => 'info',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
