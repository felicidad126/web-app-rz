<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Peran penerima jasa pada satu tindakan. */
enum CommissionRole: string implements HasLabel
{
    case Operator = 'operator';
    case Assistant = 'assistant';

    public function getLabel(): string
    {
        return match ($this) {
            self::Operator => 'Operator (dokter)',
            self::Assistant => 'Asisten',
        };
    }
}
