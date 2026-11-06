<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Oklusi (formulir odontogram). */
enum Occlusion: string implements HasLabel
{
    case Normal = 'normal';
    case Cross = 'cross';
    case Steep = 'steep';

    public function getLabel(): string
    {
        return match ($this) {
            self::Normal => 'Normal bite',
            self::Cross => 'Cross bite',
            self::Steep => 'Steep bite',
        };
    }
}
