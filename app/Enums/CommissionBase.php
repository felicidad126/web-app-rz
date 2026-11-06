<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Basis perhitungan jasa (Planning §5.7). Neto = setelah diskon baris & alokasi diskon tagihan, sebelum pajak. */
enum CommissionBase: string implements HasLabel
{
    case Gross = 'gross';
    case Net = 'net';
    case NetMinusMaterial = 'net_minus_material';
    case NetMinusLab = 'net_minus_lab';
    case NetMinusMaterialAndLab = 'net_minus_material_and_lab';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gross => 'Bruto (sebelum diskon)',
            self::Net => 'Setelah diskon',
            self::NetMinusMaterial => 'Setelah diskon − bahan',
            self::NetMinusLab => 'Setelah diskon − biaya lab',
            self::NetMinusMaterialAndLab => 'Setelah diskon − bahan − biaya lab',
        };
    }

    /** Nilai basis untuk satu baris; tidak pernah negatif. */
    public function amount(int $gross, int $net, int $material, int $lab): int
    {
        return max(0, match ($this) {
            self::Gross => $gross,
            self::Net => $net,
            self::NetMinusMaterial => $net - $material,
            self::NetMinusLab => $net - $lab,
            self::NetMinusMaterialAndLab => $net - $material - $lab,
        });
    }
}
