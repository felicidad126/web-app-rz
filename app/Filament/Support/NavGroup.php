<?php

namespace App\Filament\Support;

/** Nama grup navigasi panel (urutan diatur di AdminPanelProvider). */
final class NavGroup
{
    public const PATIENTS = 'Pasien';

    public const CLINICAL_MASTER = 'Master Klinis';

    public const INVENTORY = 'Obat & Bahan';

    public const ORGANIZATION = 'Organisasi';

    public const SETTINGS = 'Pengaturan & Akses';

    /** @return list<string> */
    public static function ordered(): array
    {
        return [self::PATIENTS, self::CLINICAL_MASTER, self::INVENTORY, self::ORGANIZATION, self::SETTINGS];
    }
}
