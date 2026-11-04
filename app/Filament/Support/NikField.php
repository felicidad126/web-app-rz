<?php

namespace App\Filament\Support;

use App\Rules\UniqueNik;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;

/** Input NIK 16 digit dengan cek duplikat via blind index. */
final class NikField
{
    /** @param class-string<Model> $model */
    public static function make(string $model): TextInput
    {
        return TextInput::make('nik')
            ->label('NIK')
            ->helperText('16 digit sesuai KTP/KK. Disimpan terenkripsi.')
            ->length(16)
            ->regex('/^\d{16}$/')
            ->validationMessages(['regex' => 'NIK harus 16 digit angka.'])
            ->rule(fn (?Model $record): UniqueNik => new UniqueNik($model, $record?->getKey()))
            ->autocomplete(false);
    }
}
