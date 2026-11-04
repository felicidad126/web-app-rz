<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Pilihan jenis penjamin. */
enum PayerType: string implements HasLabel
{
    case General = 'general';
    case Bpjs = 'bpjs';
    case Insurance = 'insurance';
    case Corporate = 'corporate';

    public function getLabel(): string
    {
        return match ($this) {
            self::General => 'Umum (bayar sendiri)',
            self::Bpjs => 'BPJS Kesehatan',
            self::Insurance => 'Asuransi Swasta',
            self::Corporate => 'Perusahaan / Corporate',
        };
    }
}
