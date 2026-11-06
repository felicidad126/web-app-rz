<?php

namespace App\Filament\Support;

/** Nama grup navigasi panel (urutan diatur di AdminPanelProvider). */
final class NavGroup
{
    public const DAILY = 'Harian';

    public const PATIENTS = 'Pasien';

    public const CLINICAL = 'Klinis';

    public const BILLING = 'Keuangan';

    public const PAYROLL = 'Jasa & Gaji';

    public const OPERATIONS = 'Operasional';

    public const REPORTS = 'Laporan';

    public const CLINICAL_MASTER = 'Master Klinis';

    public const INVENTORY = 'Master barang';

    public const ORGANIZATION = 'Organisasi';

    public const SETTINGS = 'Pengaturan & Akses';

    public const HELP = 'Bantuan';

    /** @return list<string> */
    public static function ordered(): array
    {
        return [self::DAILY, self::REPORTS, self::PATIENTS, self::CLINICAL, self::BILLING, self::PAYROLL, self::OPERATIONS, self::CLINICAL_MASTER, self::INVENTORY, self::ORGANIZATION, self::SETTINGS, self::HELP];
    }
}
