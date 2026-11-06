<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Tipe aturan jasa. */
enum CommissionType: string implements HasLabel
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percent => 'Persen dari basis',
            self::Fixed => 'Nominal tetap per tindakan',
        };
    }
}
