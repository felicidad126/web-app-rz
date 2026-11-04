<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Pilihan jenis barang. */
enum ItemType: string implements HasLabel
{
    case Medicine = 'medicine';
    case Material = 'material';
    case Tool = 'tool';
    case Retail = 'retail';

    public function getLabel(): string
    {
        return match ($this) {
            self::Medicine => 'Obat',
            self::Material => 'Bahan Dental',
            self::Tool => 'Alat Habis Pakai',
            self::Retail => 'Produk Retail',
        };
    }
}
