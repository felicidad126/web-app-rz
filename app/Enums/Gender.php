<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Pilihan jenis kelamin. */
enum Gender: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';

    public function getLabel(): string
    {
        return match ($this) {
            self::Male => 'Laki-laki',
            self::Female => 'Perempuan',
        };
    }
}
