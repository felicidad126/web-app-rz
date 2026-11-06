<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status dokumen mutasi stok. */
enum StockMovementStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Posted = 'posted';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Pending => 'Menunggu persetujuan',
            self::Posted => 'Tercatat',
            self::Rejected => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'warning',
            self::Posted => 'success',
            self::Rejected => 'danger',
        };
    }
}
