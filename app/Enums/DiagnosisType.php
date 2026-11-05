<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis diagnosa. */
enum DiagnosisType: string implements HasColor, HasLabel
{
    case Primary = 'primary';
    case Secondary = 'secondary';

    public function getLabel(): string
    {
        return match ($this) {
            self::Primary => 'Utama',
            self::Secondary => 'Sekunder',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Primary => 'primary',
            self::Secondary => 'gray',
        };
    }
}
