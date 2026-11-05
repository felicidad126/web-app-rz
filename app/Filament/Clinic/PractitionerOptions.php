<?php

namespace App\Filament\Clinic;

use App\Enums\PractitionerType;
use App\Models\Practitioner;

/** Pilihan nakes untuk form klinis. */
final class PractitionerOptions
{
    /** @return array<int, string> Dokter gigi (umum & spesialis) yang aktif. */
    public static function dentists(): array
    {
        return self::query([PractitionerType::Dentist, PractitionerType::DentalSpecialist]);
    }

    /** @return array<int, string> Semua nakes (untuk asisten/perawat). */
    public static function all(): array
    {
        return self::query(PractitionerType::cases());
    }

    /**
     * @param  list<PractitionerType>  $types
     * @return array<int, string>
     */
    private static function query(array $types): array
    {
        return Practitioner::query()
            ->whereIn('type', $types)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->with('user:id,name')
            ->get()
            ->sortBy(fn (Practitioner $p): string => $p->user->name)
            ->mapWithKeys(fn (Practitioner $p): array => [$p->id => $p->user->name])
            ->all();
    }
}
