<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Jenis baris tagihan. */
enum InvoiceItemType: string implements HasLabel
{
    case Procedure = 'procedure';
    case Item = 'item';
    case Installment = 'installment';
    case Fee = 'fee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Procedure => 'Tindakan',
            self::Item => 'Obat & bahan',
            self::Installment => 'Paket & cicilan',
            self::Fee => 'Biaya lain',
        };
    }
}
