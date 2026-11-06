<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis komponen gaji. */
enum SalaryComponentKind: string implements HasColor, HasLabel
{
    case Earning = 'earning';
    case Deduction = 'deduction';

    public function getLabel(): string
    {
        return match ($this) {
            self::Earning => 'Pendapatan',
            self::Deduction => 'Potongan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Earning => 'success',
            self::Deduction => 'danger',
        };
    }
}
