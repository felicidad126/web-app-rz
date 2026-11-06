<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Jenis pekerjaan lab. */
enum LabWorkType: string implements HasLabel
{
    case Crown = 'crown';
    case Bridge = 'bridge';
    case Denture = 'denture';
    case Retainer = 'retainer';
    case Aligner = 'aligner';
    case Veneer = 'veneer';
    case NightGuard = 'night_guard';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Crown => 'Crown',
            self::Bridge => 'Bridge',
            self::Denture => 'Gigi tiruan',
            self::Retainer => 'Retainer',
            self::Aligner => 'Aligner',
            self::Veneer => 'Veneer',
            self::NightGuard => 'Night guard',
            self::Other => 'Lainnya',
        };
    }
}
