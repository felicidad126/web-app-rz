<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status janji temu. */
enum AppointmentStatus: string implements HasColor, HasLabel
{
    case Booked = 'booked';
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::Booked => 'Terjadwal',
            self::Confirmed => 'Dikonfirmasi',
            self::Arrived => 'Datang',
            self::Cancelled => 'Dibatalkan',
            self::NoShow => 'Tidak datang',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Booked, self::Cancelled => 'gray',
            self::Confirmed => 'info',
            self::Arrived => 'warning',
            self::NoShow => 'danger',
        };
    }
}
