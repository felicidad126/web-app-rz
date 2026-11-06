<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Kapan jasa diakui (masuk rekap). */
enum CommissionRecognition: string implements HasLabel
{
    case Performed = 'performed';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Performed => 'Saat tindakan dikerjakan (tagihan terbit)',
            self::Paid => 'Saat tagihan lunas',
        };
    }
}
