<?php

namespace App\Filament\Support;

/** Nama grup navigasi panel (urutan diatur di AdminPanelProvider). */
final class NavGroup
{
    public const DAILY = 'Harian';

    public const PATIENTS = 'Pasien';

    public const CLINICAL = 'Klinis';

    public const BILLING = 'Keuangan';

    public const OPERATIONS = 'Operasional';

    public const CLINICAL_MASTER = 'Master Klinis';

    public const INVENTORY = 'Obat & Bahan';

    public const ORGANIZATION = 'Organisasi';

    public const SETTINGS = 'Pengaturan & Akses';

    /** @return list<string> */
    public static function ordered(): array
    {
        return [self::DAILY, self::PATIENTS, self::CLINICAL, self::BILLING, self::OPERATIONS, self::CLINICAL_MASTER, self::INVENTORY, self::ORGANIZATION, self::SETTINGS];
    }
}
