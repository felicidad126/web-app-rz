<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Pilihan cakupan kondisi odontogram. */
enum OdontogramScope: string implements HasLabel
{
    case Tooth = 'tooth';
    case Surface = 'surface';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tooth => 'Per gigi',
            self::Surface => 'Per permukaan',
        };
    }
}
