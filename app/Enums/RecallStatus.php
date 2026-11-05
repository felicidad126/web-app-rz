<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status recall (pengingat kontrol). */
enum RecallStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Booked = 'booked';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Belum dihubungi',
            self::Contacted => 'Sudah dihubungi',
            self::Booked => 'Sudah buat janji',
            self::Done => 'Selesai',
            self::Cancelled => 'Batal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Contacted => 'info',
            self::Booked => 'success',
            self::Done, self::Cancelled => 'gray',
        };
    }
}
