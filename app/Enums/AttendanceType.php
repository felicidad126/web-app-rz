<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Jenis kehadiran dokter yang dibayar. */
enum AttendanceType: string implements HasLabel
{
    case Present = 'present';
    case OnCall = 'on_call';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::OnCall => 'On call',
        };
    }
}
