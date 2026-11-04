<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Peran & hak akses sesuai matriks akses Planning v2 (Bagian 4.3), lingkup Fase 1.
 *
 * Format permission mengikuti Filament Shield: "{Aksi}:{Model}", mis. "ViewAny:Patient".
 * Seeder ini idempoten: aman dijalankan ulang setelah menambah resource.
 */
class RolePermissionSeeder extends Seeder
{
    public const ROLES = ['owner', 'admin', 'dokter', 'perawat', 'resepsionis', 'kasir'];

    private const CRUD = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'RestoreAny'];

    private const CRU = ['ViewAny', 'View', 'Create', 'Update'];

    private const RU = ['ViewAny', 'View', 'Update'];

    private const R = ['ViewAny', 'View'];

    private const MASTER = ['Treatment', 'Icd10Code', 'Icd9cmCode', 'OdontogramCondition', 'Item', 'Unit'];

    private const ORGANIZATION = ['Branch', 'User', 'Practitioner', 'Payer'];

    /**
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $owner = [
            ...self::grant([...self::ORGANIZATION, ...self::MASTER, 'Patient', 'Role'], self::CRUD),
            ...self::grant(['Activity'], self::R),
            'View:SipExpiryWidget',
        ];

        $admin = [
            ...self::grant([...self::ORGANIZATION, ...self::MASTER, 'Patient'], self::CRUD),
            ...self::grant(['Role', 'Activity'], self::R),
            'View:SipExpiryWidget',
        ];

        $clinical = [
            ...self::grant([...self::MASTER, 'Payer'], self::R),
            ...self::grant(['Patient'], self::RU),
        ];

        return [
            'owner' => $owner,
            'admin' => $admin,
            'dokter' => $clinical,
            'perawat' => $clinical,
            // Resepsionis mendaftarkan & mengubah data pasien, tetapi tidak menghapus (retensi rekam medis).
            'resepsionis' => [
                ...self::grant(['Treatment', 'Payer'], self::R),
                ...self::grant(['Patient'], self::CRU),
            ],
            'kasir' => [
                ...self::grant(['Treatment', 'Item', 'Payer', 'Patient'], self::R),
            ],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(config('filament-shield.super_admin.name'), 'web');

        foreach (self::matrix() as $roleName => $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $subjects
     * @param  list<string>  $actions
     * @return list<string>
     */
    private static function grant(array $subjects, array $actions): array
    {
        $permissions = [];

        foreach ($subjects as $subject) {
            foreach ($actions as $action) {
                $permissions[] = "{$action}:{$subject}";
            }
        }

        return $permissions;
    }
}
