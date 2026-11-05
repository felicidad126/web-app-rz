<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis entri odontogram. */
enum OdontogramEntryType: string implements HasColor, HasLabel
{
    case Existing = 'existing';
    case Planned = 'planned';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Existing => 'Kondisi',
            self::Planned => 'Rencana',
            self::Done => 'Dikerjakan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Existing => 'gray',
            self::Planned => 'danger',
            self::Done => 'info',
        };
    }
}
