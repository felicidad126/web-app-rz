<?php

namespace App\Filament\Clinic;

use App\Models\Patient;
use App\Support\BlindIndex;
use Filament\Forms\Components\Select;

/** Select pasien dengan pencarian nama, No. RM, HP, atau NIK lengkap. */
final class PatientSelect
{
    public static function make(string $name = 'patient_id'): Select
    {
        return Select::make($name)
            ->label('Pasien')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::search($search))
            ->getOptionLabelUsing(fn ($value): ?string => ($p = Patient::find($value)) ? self::label($p) : null)
            ->searchPrompt('Ketik nama, No. RM, No. HP, atau NIK')
            ->noSearchResultsMessage('Pasien tidak ditemukan. Daftarkan dulu di menu Data Pasien.')
            ->required();
    }

    /** @return array<int, string> */
    public static function search(string $search): array
    {
        $search = trim($search);

        return Patient::query()
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('rm_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");

                if (strlen(BlindIndex::normalize($search) ?? '') === 16) {
                    $query->orWhere('nik_hash', BlindIndex::hash($search));
                }
            })
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Patient $p): array => [$p->id => self::label($p)])
            ->all();
    }

    public static function label(Patient $patient): string
    {
        return $patient->name.' · '.$patient->rm_number.' · '.$patient->birth_date->format('d/m/Y');
    }
}
