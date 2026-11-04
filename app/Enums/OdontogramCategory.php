<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Pilihan kategori kondisi odontogram. */
enum OdontogramCategory: string implements HasLabel
{
    case Condition = 'condition';
    case Restoration = 'restoration';
    case Prosthesis = 'prosthesis';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Condition => 'Kondisi',
            self::Restoration => 'Restorasi',
            self::Prosthesis => 'Prostetik',
            self::Other => 'Lainnya',
        };
    }
}
