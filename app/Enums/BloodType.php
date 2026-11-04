<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Pilihan golongan darah. */
enum BloodType: string implements HasLabel
{
    case A = 'A';
    case B = 'B';
    case AB = 'AB';
    case O = 'O';

    public function getLabel(): string
    {
        return match ($this) {
            self::A => 'A',
            self::B => 'B',
            self::AB => 'AB',
            self::O => 'O',
        };
    }
}
