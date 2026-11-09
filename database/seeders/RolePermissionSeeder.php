<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Peran & hak akses edisi inventori (branch `inventori`): owner, admin, perawat; hanya master barang, stok,
 * pembelian, kas pembelian, laporan stok, pengguna, cabang, dan audit log.
 *
 * Format permission mengikuti Filament Shield: "{Aksi}:{Model}", mis. "ViewAny:Item".
 * Seeder ini idempoten: aman dijalankan ulang setelah menambah resource.
 */
class RolePermissionSeeder extends Seeder
{
    public const ROLES = ['owner', 'admin', 'perawat'];

    private const CRUD = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'RestoreAny'];

    private const CRU = ['ViewAny', 'View', 'Create', 'Update'];

    private const R = ['ViewAny', 'View'];

    private const MASTER = ['Item', 'Unit', 'ItemCategory', 'Supplier'];

    private const ORGANIZATION = ['Branch', 'User'];

    private const STOCK = ['StockMovement', 'StockOpname'];

    /**
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $reports = ['View:Reports', 'View:FinancialReports'];
        $purchaseCash = ['View:PurchaseCash', 'Manage:PurchaseCash'];

        return [
            // Owner: semua master & pengguna, memantau stok/pesanan, menyetujui pemusnahan, opname & permintaan, kas pembelian.
            'owner' => [
                ...self::grant([...self::ORGANIZATION, ...self::MASTER, 'Role'], self::CRUD),
                ...self::grant(['Activity', ...self::STOCK, 'PurchaseRequest', 'PurchaseOrder'], self::R),
                'View:Stock', 'Approve:Stock', 'Approve:Purchase',
                ...$purchaseCash,
                ...$reports,
            ],
            // Admin: mengelola master, stok, pesanan ke supplier, menyetujui, menerima barang, kas pembelian.
            'admin' => [
                ...self::grant([...self::ORGANIZATION, ...self::MASTER], self::CRUD),
                ...self::grant(['Role', 'Activity'], self::R),
                ...self::grant([...self::STOCK, 'PurchaseRequest', 'PurchaseOrder'], self::CRU),
                'View:Stock', 'Approve:Stock', 'Approve:Purchase', 'Receive:Purchase',
                ...$purchaseCash,
                ...$reports,
            ],
            // Perawat: mencatat penerimaan & pemakaian, mengajukan pemusnahan, menghitung opname, minta beli, terima barang pesanan.
            'perawat' => [
                ...self::grant(self::MASTER, self::R),
                ...self::grant([...self::STOCK, 'PurchaseRequest'], self::CRU),
                ...self::grant(['PurchaseOrder'], self::R),
                'View:Stock', 'Receive:Purchase',
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
