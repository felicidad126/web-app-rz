<?php

namespace App\Filament\Clinic;

use App\Enums\ToothSurface;
use Filament\Forms\Components\ToggleButtons;

/**
 * Pilihan permukaan gigi (M O D V L) sebagai tombol besar untuk tablet.
 * Disimpan sebagai teks berurutan baku, mis. "MOD".
 */
final class SurfacesField
{
    public static function make(string $name = 'surfaces'): ToggleButtons
    {
        return ToggleButtons::make($name)
            ->label('Permukaan')
            ->options(collect(ToothSurface::cases())->mapWithKeys(fn (ToothSurface $s): array => [$s->value => $s->value])->all())
            ->multiple()
            ->inline()
            ->dehydrateStateUsing(fn ($state): ?string => ToothSurface::normalize(implode('', (array) $state)));
    }

    /**
     * Untuk Repeater::mutateRelationshipDataBeforeFillUsing(): teks "MO" menjadi ['M', 'O']
     * sebelum form diisi (field multiple hanya menerima array).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function splitForFill(array $data, string $name = 'surfaces'): array
    {
        $value = $data[$name] ?? null;
        $data[$name] = is_string($value) && $value !== '' ? str_split($value) : [];

        return $data;
    }
}
