<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status item rencana perawatan. */
enum TreatmentPlanItemStatus: string implements HasColor, HasLabel
{
    case Planned = 'planned';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::Done => 'Dikerjakan',
            self::Cancelled => 'Batal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned, self::Cancelled => 'gray',
            self::Done => 'success',
        };
    }
}
