<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Penyebab selisih kas saat tutup kasir. */
enum CashDifferenceReason: string implements HasLabel
{
    case ShortChange = 'short_change';
    case RejectedNote = 'rejected_note';
    case Unknown = 'unknown';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::ShortChange => 'Kembalian kurang',
            self::RejectedNote => 'Uang ditolak',
            self::Unknown => 'Belum diketahui',
            self::Other => 'Lainnya',
        };
    }
}
