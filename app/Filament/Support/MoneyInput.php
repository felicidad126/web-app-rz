<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * Isian rupiah: "Rp" di depan, pemisah ribuan titik saat mengetik ("1.250.000"), disimpan sebagai bilangan bulat.
 */
final class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return self::configure(TextInput::make($name));
    }

    public static function configure(TextInput $input): TextInput
    {
        return $input
            ->prefix('Rp')
            ->numeric()
            ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
            ->stripCharacters('.');
    }
}
