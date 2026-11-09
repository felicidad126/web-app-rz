<?php

namespace Database\Seeders;

use App\Enums\ItemType;
use App\Enums\StockMovementType;
use App\Enums\StockReason;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\PurchaseRequest;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Purchasing\Purchasing;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockOpnames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Data demo edisi inventori (branch `inventori`): akun owner/admin/perawat, dua cabang, barang BHP & obat,
 * supplier, stok awal lewat opname, penerimaan, transfer, pemusnahan menunggu persetujuan, dan alur pembelian.
 * Semua data fiktif. Aman dijalankan ulang (bagian stok & pembelian dibuat sekali).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di produksi.');
        }

        $this->call(DatabaseSeeder::class);

        // Cabang utama = alamat klinik East Family Dental Care; cabang kedua fiktif untuk fitur multi-cabang.
        $main = $this->demoBranch('PDR', 'KMG', [
            'name' => 'Pondok Ranggon',
            'address' => 'Jl. Pd. Ranggon, RT.2/RW.3, Pd. Ranggon, Kec. Cipayung, Kota Jakarta Timur, DKI Jakarta 13860',
            'phone' => null,
        ]);
        $second = $this->demoBranch('CTH', 'DPK', ['name' => 'Cabang contoh', 'address' => 'Alamat contoh (data demo)', 'phone' => null]);

        $accounts = [
            ['owner@demo.test', 'Drg. Ratna Owner', 'owner', [$main, $second]],
            ['admin@demo.test', 'Sari Admin', 'admin', [$main, $second]],
            ['perawat@demo.test', 'Dewi Perawat', 'perawat', [$main]],
            ['perawat2@demo.test', 'Lina Perawat', 'perawat', [$second]],
        ];

        foreach ($accounts as [$email, $name, $role, $branches]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => config('clinic.demo_password'), 'is_active' => true]);
            $user->syncRoles([$role]);
            $user->branches()->syncWithoutDetaching(collect($branches)->pluck('id'));
        }

        $this->seedItems();
        $this->seedStock($main, $second);
        $this->seedPurchasing($main);
    }

    /**
     * Cabang demo berdasarkan kode; cabang dari data demo lama ($legacyCode) diganti di tempat.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function demoBranch(string $code, string $legacyCode, array $attributes): Branch
    {
        $branch = Branch::where('code', $code)->first() ?? Branch::where('code', $legacyCode)->first() ?? new Branch;
        $branch->fill(['code' => $code, ...$attributes])->save();

        return $branch;
    }

    /** Barang & kategori mirip daftar BHP klinik; satuan dasar + konversi kemasan. */
    private function seedItems(): void
    {
        $unit = fn (string $name): int => (int) Unit::where('name', $name)->value('id');

        // [kode, nama, jenis, kategori, satuan dasar, minimum, konversi kemasan]
        $items = [
            ['BHP-MSK', 'Masker medis 3 ply', ItemType::Tool, 'Disposable', 'pcs', 100, ['box' => 50]],
            ['BHP-GLV', 'Handscoon nitril M', ItemType::Tool, 'Disposable', 'pcs', 200, ['box' => 100]],
            ['BHP-GLS', 'Gelas kumur plastik', ItemType::Tool, 'Disposable', 'pcs', 100, ['pack' => 50]],
            ['BHP-BIB', 'Dental bib', ItemType::Tool, 'Disposable', 'pcs', 100, ['pack' => 125]],
            ['BHP-SUC', 'Suction tip', ItemType::Tool, 'Disposable', 'pcs', 50, ['pack' => 100]],
            ['BHP-ALG', 'Alginate', ItemType::Material, 'Bahan cetak', 'gram', 500, ['pack' => 453]],
            ['BHP-PLS', 'Dental plaster', ItemType::Material, 'Bahan cetak', 'gram', 1000, ['pack' => 1000]],
            ['BHP-BRK', 'Bracket metal standar', ItemType::Material, 'Ortodontik', 'set', 3, []],
            ['BHN-LIDO', 'Lidokain 2% + epinefrin', ItemType::Material, 'Anestesi', 'cartridge', 20, ['box' => 50]],
            ['BHN-KOMP', 'Resin komposit A2', ItemType::Material, 'Bahan restorasi', 'tube', 3, []],
            ['BHN-GIC', 'Glass ionomer cement', ItemType::Material, 'Bahan restorasi', 'pack', 2, []],
            ['OBT-AMX500', 'Amoxicillin 500 mg', ItemType::Medicine, 'Obat', 'tablet', 50, ['strip' => 10, 'box' => 100]],
            ['OBT-MEF500', 'Asam mefenamat 500 mg', ItemType::Medicine, 'Obat', 'tablet', 50, ['strip' => 10, 'box' => 100]],
        ];

        foreach ($items as [$sku, $name, $type, $categoryName, $base, $min, $conversions]) {
            $category = ItemCategory::firstOrCreate(['name' => $categoryName]);
            $item = Item::firstOrCreate(['sku' => $sku], [
                'name' => $name, 'type' => $type, 'item_category_id' => $category->id, 'base_unit_id' => $unit($base),
                'min_stock' => $min, 'track_expiry' => $type !== ItemType::Tool,
            ]);

            foreach ($conversions as $unitName => $factor) {
                $item->units()->firstOrCreate(['unit_id' => $unit($unitName)], ['factor_to_base' => $factor]);
            }
        }

        Supplier::firstOrCreate(['name' => 'PT Medika Jaya'], ['contact_person' => 'Pak Hendra', 'phone' => '021-5550101']);
        Supplier::firstOrCreate(['name' => 'CV Dental Supply'], ['contact_person' => 'Bu Lina', 'phone' => '021-5550202']);
    }

    /** Stok awal lewat opname 30 hari lalu, satu penerimaan, satu transfer, satu pemusnahan menunggu persetujuan. */
    private function seedStock(Branch $main, Branch $second): void
    {
        if (StockOpname::where('branch_id', $main->id)->exists()) {
            return;
        }

        $perawat = User::where('email', 'perawat@demo.test')->firstOrFail();
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();
        $item = fn (string $sku): Item => Item::where('sku', $sku)->firstOrFail();
        $box = (int) Unit::where('name', 'box')->value('id');
        $today = today();
        $ed = fn (int $days): string => $today->copy()->addDays($days)->endOfMonth()->toDateString();

        // [jumlah, harga pokok per satuan dasar, batch, ED]
        $counts = [
            'BHP-MSK' => [300, 450, 'MSK-01', null],
            'BHP-GLV' => [600, 650, 'GLV-01', null],
            'BHP-GLS' => [80, 300, 'GLS-01', null],                          // di bawah minimum (100)
            'BHP-BIB' => [250, 400, 'BIB-01', null],
            'BHP-SUC' => [120, 500, 'SUC-01', null],
            'BHP-ALG' => [900, 120, 'ALG-2604', $ed(150)],
            'BHP-PLS' => [2500, 30, 'PLS-01', null],
            'BHP-BRK' => [5, 350_000, 'BRK-01', null],
            'BHN-LIDO' => [35, 9_800, 'LD-0612', $today->copy()->addDays(45)->toDateString()],  // hampir kedaluwarsa
            'BHN-KOMP' => [4, 285_000, 'KMP-2208', $ed(330)],
            'BHN-GIC' => [1, 420_000, 'GIC-2105', $today->copy()->subDays(10)->toDateString()],  // sudah kedaluwarsa
            'OBT-AMX500' => [180, 1_200, 'AMX-2409', $ed(240)],
            'OBT-MEF500' => [40, 900, 'MEF-2511', $ed(420)],                 // di bawah minimum (50)
        ];

        Carbon::setTestNow($today->copy()->subDays(30)->setTime(17, 0));
        $opname = StockOpnames::start($main->id, $perawat, 'Opname awal (saldo awal sistem)');

        foreach ($opname->lines()->with('item')->get() as $line) {
            [$qty, $cost, $batch, $expiry] = $counts[$line->item->sku] ?? [0, 0, null, null];
            $line->update(['counted_qty' => $qty, 'unit_cost' => $cost, 'batch_number' => $batch, 'expired_at' => $expiry]);
        }

        StockOpnames::submit($opname, $perawat);
        Carbon::setTestNow($today->copy()->subDays(29)->setTime(8, 30));
        StockOpnames::approve($opname->refresh(), $admin);

        // Penerimaan langsung (tanpa pesanan) seminggu lalu.
        Carbon::setTestNow($today->copy()->subDays(7)->setTime(11, 0));
        StockLedger::receive($main->id, [
            ['item_id' => $item('BHN-LIDO')->id, 'unit_id' => $box, 'qty' => 1, 'unit_price' => 490_000, 'batch_number' => 'LD-0711', 'expired_at' => $ed(420)],
            ['item_id' => $item('BHP-MSK')->id, 'unit_id' => $box, 'qty' => 2, 'unit_price' => 25_000, 'batch_number' => 'MSK-02', 'expired_at' => null],
        ], Supplier::where('name', 'PT Medika Jaya')->value('id'), 'MJ/2611/0457', today(), null, $perawat);

        // Pemakaian harian.
        Carbon::setTestNow($today->copy()->subDays(3)->setTime(15, 0));
        StockLedger::issue($main->id, StockMovementType::Usage, [
            ['item_id' => $item('BHP-GLV')->id, 'unit_id' => $item('BHP-GLV')->base_unit_id, 'qty' => 40],
            ['item_id' => $item('BHP-MSK')->id, 'unit_id' => $item('BHP-MSK')->base_unit_id, 'qty' => 20],
        ], null, 'Pemakaian harian Unit 1 & 2', $perawat);

        // Kirim handscoon ke cabang contoh.
        Carbon::setTestNow($today->copy()->subDays(5)->setTime(14, 0));
        StockLedger::transfer($main->id, $second->id, [
            ['item_id' => $item('BHP-GLV')->id, 'unit_id' => $item('BHP-GLV')->base_unit_id, 'qty' => 100],
        ], 'Stok cabang contoh menipis', $admin);

        // Pemusnahan GIC kedaluwarsa: diajukan perawat, menunggu persetujuan admin/owner.
        Carbon::setTestNow($today->copy()->subDay()->setTime(16, 0));
        $gic = ItemBatch::where('branch_id', $main->id)->where('batch_number', 'GIC-2105')->firstOrFail();
        StockLedger::requestDisposal($main->id, [['item_batch_id' => $gic->id, 'qty' => (float) $gic->qty_on_hand]], StockReason::Expired, 'Dimusnahkan bersama limbah B3 klinik, saksi: Dewi Perawat.', $perawat);

        Carbon::setTestNow();
    }

    /**
     * Pembelian: kas pembelian, permintaan perawat (menunggu, disetujui), pesanan yang sudah dibayar dan baru
     * diterima sebagian (lewat perkiraan datang), dan ongkos kirim.
     */
    private function seedPurchasing(Branch $main): void
    {
        if (PurchaseRequest::where('branch_id', $main->id)->exists()) {
            return;
        }

        $owner = User::where('email', 'owner@demo.test')->firstOrFail();
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();
        $perawat = User::where('email', 'perawat@demo.test')->firstOrFail();
        $item = fn (string $sku): Item => Item::where('sku', $sku)->firstOrFail();
        $box = (int) Unit::where('name', 'box')->value('id');
        $supply = Supplier::where('name', 'CV Dental Supply')->firstOrFail();
        $today = today();
        $at = fn (int $daysAgo, int $hour) => Carbon::setTestNow($today->copy()->subDays($daysAgo)->setTime($hour, 0));

        $at(10, 9);
        Purchasing::deposit($main->id, 2_000_000, 'Setoran awal kas pembelian dari owner', $owner);

        $at(9, 10);
        $mef = Purchasing::requestItem($main->id, $item('OBT-MEF500')->id, 2, $box, 'Stok 40 tablet, di bawah minimum 50', $perawat);
        $at(9, 13);
        Purchasing::approve($mef, $admin);
        $order = Purchasing::createOrder($main->id, $supply->id, today(), [
            ['item_id' => $item('OBT-MEF500')->id, 'unit_id' => $box, 'qty' => 2, 'unit_price' => 85_000, 'purchase_request_id' => $mef->id],
            ['item_id' => $item('OBT-AMX500')->id, 'unit_id' => $box, 'qty' => 1, 'unit_price' => 110_000],
        ], $admin, $today->copy()->subDays(3), 'Kirim ke klinik Pondok Ranggon', payNow: true);

        $at(6, 11);
        $lines = $order->lines()->get();
        Purchasing::receive($order, [
            ['line_id' => $lines[0]->id, 'qty' => 1, 'batch_number' => 'MEF-2611', 'expired_at' => $today->copy()->addMonths(18)->endOfMonth()->toDateString()],
            ['line_id' => $lines[1]->id, 'qty' => 1, 'batch_number' => 'AMX-2611', 'expired_at' => $today->copy()->addMonths(20)->endOfMonth()->toDateString()],
        ], $perawat, today(), 'CVDS/2611/112');
        Purchasing::expense($main->id, 20_000, 'Ongkos kirim CV Dental Supply', $admin);

        $at(2, 15);
        $cups = Purchasing::requestItem($main->id, $item('BHP-GLS')->id, 2, (int) Unit::where('name', 'pack')->value('id'), 'Stok tinggal 80, di bawah minimum 100', $perawat);
        $at(2, 17);
        Purchasing::approve($cups, $owner);

        $at(1, 16);
        Purchasing::requestItem($main->id, $item('BHN-KOMP')->id, 2, $item('BHN-KOMP')->base_unit_id, 'Persiapan 3 kasus estetik minggu depan', $perawat);

        Carbon::setTestNow();
    }
}
