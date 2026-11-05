<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Hubungan penanda tangan persetujuan dengan pasien. */
enum SignerRelation: string implements HasColor, HasLabel
{
    case Self = 'self';
    case Parent = 'parent';
    case Guardian = 'guardian';
    case Spouse = 'spouse';
    case Child = 'child';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Self => 'Pasien sendiri',
            self::Parent => 'Orang tua',
            self::Guardian => 'Wali',
            self::Spouse => 'Suami/istri',
            self::Child => 'Anak',
            self::Other => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Self, self::Parent, self::Guardian, self::Spouse, self::Child, self::Other => 'gray',
        };
    }
}
