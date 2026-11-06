<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Palate: string implements HasLabel
{
    case Deep = 'deep';
    case Medium = 'medium';
    case Shallow = 'shallow';

    public function getLabel(): string
    {
        return match ($this) {
            self::Deep => 'Dalam',
            self::Medium => 'Sedang',
            self::Shallow => 'Rendah',
        };
    }
}
