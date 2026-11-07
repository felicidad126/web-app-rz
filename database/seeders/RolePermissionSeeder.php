<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Peran & hak akses sesuai matriks akses Planning v2 (Bagian 4.3), lingkup Fase 1–7 + pembelian.
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

    private const MASTER = ['Treatment', 'Icd10Code', 'Icd9cmCode', 'OdontogramCondition', 'Item', 'Unit', 'ItemCategory', 'Supplier'];

    private const ORGANIZATION = ['Branch', 'User', 'Practitioner', 'Payer'];

    /**
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        // Rekam medis tidak punya resource sendiri; izinnya dicek langsung (View/Update/Sign:MedicalRecord).
        $readRecord = ['View:MedicalRecord'];
        $writeRecord = ['View:MedicalRecord', 'Update:MedicalRecord'];
        $signRecord = [...$writeRecord, 'Sign:MedicalRecord'];

        // Kasir & billing: kasir mengajukan void/refund, owner menyetujui (Approve:Billing). Deposit tanpa resource sendiri.
        $billingRead = [...self::grant(['Invoice', 'CashSession', 'PatientPackage'], self::R), 'View:Deposit'];

        // Fase 6: laporan, briefing pagi, dan widget dashboard (Planning §5.8). Laba hanya owner.
        $reportsAll = ['View:Reports', 'View:FinancialReports', 'View:MorningHuddle'];
        $ownerWidgets = ['View:BranchPerformanceWidget', 'View:ProductionTrendWidget', 'View:DoctorPerformanceWidget', 'View:UnscheduledPlansWidget', 'View:OverdueBalancesWidget'];

        $owner = [
            ...self::grant([...self::ORGANIZATION, ...self::MASTER, 'ConsentTemplate', 'Patient', 'Role', 'Package'], self::CRUD),
            ...self::grant(['Activity', 'Appointment', 'Visit', 'TreatmentPlan', 'Recall'], self::R),
            ...$readRecord,
            ...$billingRead,
            'Approve:Billing',
            'View:SatuSehat',
            // Stok: owner melihat dan ikut menyetujui pemusnahan/koreksi opname.
            ...self::grant(['StockMovement', 'StockOpname', 'LabOrder'], self::R),
            'View:Stock',
            'Approve:Stock',
            // Pembelian: owner memantau, menyetujui permintaan, dan mengelola kas pembelian.
            ...self::grant(['PurchaseRequest', 'PurchaseOrder'], self::R),
            'Approve:Purchase',
            'View:PurchaseCash',
            'Manage:PurchaseCash',
            'View:SipExpiryWidget',
            'View:CashReport',
            // Jasa dokter & gaji: owner mengatur aturan, membuat & membayar rekap, mengelola gaji (Planning §4.3).
            ...self::grant(['CommissionRule', 'SalaryComponent', 'Target'], self::CRUD),
            ...self::grant(['Commission', 'CommissionPayout', 'Payslip'], self::CRU),
            ...self::grant(['PractitionerAttendance'], self::CRUD),
            'Manage:Commission',
            'ViewAll:Commission',
            'Manage:Payroll',
            // Laporan & dashboard: semua laporan termasuk laba.
            ...$reportsAll,
            'View:ProfitReports',
            ...$ownerWidgets,
            'View:TopTreatmentsWidget',
            'View:TopDiagnosesWidget',
        ];

        $admin = [
            ...self::grant([...self::ORGANIZATION, ...self::MASTER, 'ConsentTemplate', 'Patient', 'Appointment', 'Visit', 'Recall', 'Package'], self::CRUD),
            ...self::grant(['Role', 'Activity'], self::R),
            ...$billingRead,
            // SATUSEHAT: admin memantau, kirim ulang, dan mengatur Location cabang.
            'View:SatuSehat',
            'Resend:SatuSehat',
            // Stok & lab: admin klinik mengelola dan menyetujui (keputusan owner 6 Nov 2026).
            ...self::grant(['StockMovement', 'StockOpname', 'LabOrder'], self::CRU),
            'View:Stock',
            'Approve:Stock',
            // Pembelian: admin menyetujui permintaan perawat, memesan ke supplier, menerima, dan mengelola kas pembelian.
            ...self::grant(['PurchaseRequest', 'PurchaseOrder'], self::CRU),
            'Approve:Purchase',
            'Receive:Purchase',
            'View:PurchaseCash',
            'Manage:PurchaseCash',
            'View:SipExpiryWidget',
            'View:CashReport',
            // Jasa & gaji: admin melihat (R) dan mencatat kehadiran dokter harian.
            ...self::grant(['CommissionRule', 'Commission', 'CommissionPayout', 'SalaryComponent', 'Payslip', 'Target'], self::R),
            ...self::grant(['PractitionerAttendance'], self::CRU),
            'ViewAll:Commission',
            // Laporan keuangan tanpa laba.
            ...$reportsAll,
            ...$ownerWidgets,
            'View:TopDiagnosesWidget',
            'View:FrontDeskWidget',
            'View:ToContactWidget',
        ];

        $clinical = [
            ...self::grant([...self::MASTER, 'Payer', 'ConsentTemplate'], self::R),
            ...self::grant(['Patient'], self::RU),
            ...self::grant(['TreatmentPlan', 'Recall'], self::CRU),
        ];

        return [
            'owner' => $owner,
            'admin' => $admin,
            // Dokter: jadwal miliknya (dibatasi di query), bisa memanggil pasien ke kursi, menandatangani rekam medis.
            'dokter' => [
                ...$clinical, ...self::grant(['Appointment', 'Package', 'PatientPackage', 'StockMovement'], self::R), ...self::grant(['Visit'], self::RU),
                ...self::grant(['LabOrder'], self::CRU), 'View:Stock', ...$signRecord,
                // Jasa miliknya saja (query dibatasi bila tidak punya ViewAll:Commission); laporan miliknya.
                ...self::grant(['Commission', 'CommissionPayout'], self::R),
                'View:Reports', 'View:MorningHuddle', 'View:MyDayWidget', 'View:MyPerformanceWidget',
            ],
            // Perawat: mengisi draf rekam medis, tidak menandatangani.
            // Perawat juga mencatat penerimaan, pemakaian, pengajuan pemusnahan, dan menghitung opname (tanpa menyetujui).
            'perawat' => [
                ...$clinical, ...self::grant(['Appointment', 'Visit'], self::RU), ...$writeRecord, 'View:SatuSehat',
                ...self::grant(['StockMovement', 'StockOpname', 'LabOrder'], self::CRU), 'View:Stock',
                // Perawat meminta barang dan mencatat barang pesanan yang datang (tanpa memesan/menyetujui).
                ...self::grant(['PurchaseRequest'], self::CRU), ...self::grant(['PurchaseOrder'], self::R), 'Receive:Purchase',
                'View:MorningHuddle', 'View:TopDiagnosesWidget',
            ],
            // Resepsionis mendaftarkan & mengubah data pasien, tetapi tidak menghapus (retensi rekam medis).
            'resepsionis' => [
                ...self::grant(['Treatment', 'Payer', 'Invoice', 'Package', 'PatientPackage'], self::R),
                ...self::grant(['Patient', 'Appointment', 'Visit', 'Recall'], self::CRU),
                'View:Deposit',
                // Resepsionis memperbaiki NIK pasien yang membuat kiriman tertahan, lalu kirim ulang.
                'View:SatuSehat',
                'Resend:SatuSehat',
                // Resepsionis memantau order lab (barang datang, jadwal pasang).
                ...self::grant(['LabOrder'], self::RU),
                'View:MorningHuddle', 'View:FrontDeskWidget', 'View:ToContactWidget',
            ],
            // Kasir: tagihan, pembayaran, sesi kasir, paket pasien, deposit. Tidak menghapus apa pun.
            'kasir' => [
                ...self::grant(['Treatment', 'Item', 'Payer', 'Patient', 'Appointment', 'Visit', 'Package'], self::R),
                ...self::grant(['Invoice', 'CashSession', 'PatientPackage'], self::CRU),
                'View:Deposit',
                'TopUp:Deposit',
                'View:Stock',
                // Kas harian: laporan arus kas & widget kasir.
                'View:Reports', 'View:CashReport', 'View:CashierTodayWidget',
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
