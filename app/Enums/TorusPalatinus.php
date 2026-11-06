<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TorusPalatinus: string implements HasLabel
{
    case None = 'none';
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
    case Multiple = 'multiple';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Tidak ada',
            self::Small => 'Kecil',
            self::Medium => 'Sedang',
            self::Large => 'Besar',
            self::Multiple => 'Multipel',
        };
    }
}
