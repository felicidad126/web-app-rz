<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status rencana perawatan. */
enum TreatmentPlanStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Presented = 'presented';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case PartiallyDone = 'partially_done';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Presented => 'Diajukan ke pasien',
            self::Accepted => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::PartiallyDone => 'Sebagian selesai',
            self::Done => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Presented => 'info',
            self::Accepted, self::Done => 'success',
            self::Rejected => 'danger',
            self::PartiallyDone => 'warning',
        };
    }
}
