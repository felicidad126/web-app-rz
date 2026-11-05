<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status kunjungan. */
enum VisitStatus: string implements HasColor, HasLabel
{
    case Waiting = 'waiting';
    case InTreatment = 'in_treatment';
    case Done = 'done';
    case Billed = 'billed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu',
            self::InTreatment => 'Di kursi',
            self::Done => 'Selesai',
            self::Billed => 'Sudah ditagih',
            self::Cancelled => 'Batal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Waiting => 'warning',
            self::InTreatment => 'violet',
            self::Done => 'success',
            self::Billed, self::Cancelled => 'gray',
        };
    }
}
