<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Alasan pemusnahan / koreksi stok. */
enum StockReason: string implements HasLabel
{
    case Expired = 'expired';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Correction = 'correction';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Expired => 'Kedaluwarsa',
            self::Damaged => 'Rusak',
            self::Lost => 'Hilang',
            self::Correction => 'Koreksi hitung',
            self::Other => 'Lainnya',
        };
    }
}
