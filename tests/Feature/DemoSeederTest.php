<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\StockMovementStatus;
use App\Enums\StockOpnameStatus;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\User;
use App\Services\Purchasing\Purchasing;
use App\Services\Stock\StockAlerts;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;

it('mengisi data demo inventori tanpa factory/Faker dan memakai password dari config', function () {
    config(['clinic.demo_password' => 'demo-rahasia-123']);

    $this->seed(DemoSeeder::class);

    // Akun edisi inventori saja (owner, admin, perawat), tanpa data klinik.
    expect(User::pluck('email')->sort()->values()->all())->toBe(['admin@demo.test', 'owner@demo.test', 'perawat2@demo.test', 'perawat@demo.test'])
        ->and(Hash::check('demo-rahasia-123', User::where('email', 'owner@demo.test')->value('password')))->toBeTrue()
        ->and(User::where('email', 'perawat@demo.test')->first()->hasRole('perawat'))->toBeTrue()
        ->and(Patient::count())->toBe(0);

    // Stok: opname awal disetujui, peringatan stok terlihat, pemusnahan menunggu persetujuan.
    $main = Branch::where('code', 'PDR')->firstOrFail();
    expect($main)->name->toBe('Pondok Ranggon')->address->toContain('Kec. Cipayung, Kota Jakarta Timur');
    expect(StockOpname::sole()->status)->toBe(StockOpnameStatus::Approved)
        ->and(StockAlerts::belowMinimum($main->id))->not->toBeEmpty()
        ->and(StockAlerts::expiring($main->id))->not->toBeEmpty()
        ->and(StockMovement::where('status', StockMovementStatus::Pending)->count())->toBe(1);

    // Pembelian: satu permintaan menunggu, satu siap dipesan, pesanan dibayar & diterima sebagian, saldo kas sisa.
    expect(PurchaseRequest::where('status', PurchaseRequestStatus::Pending)->count())->toBe(1)
        ->and(PurchaseRequest::where('status', PurchaseRequestStatus::Approved)->count())->toBe(1)
        ->and(PurchaseOrder::sole())->status->toBe(PurchaseOrderStatus::Partial)->paid_amount->toBe(280_000)
        ->and(Purchasing::balance($main->id))->toBe(2_000_000 - 280_000 - 20_000);

    // Aman dijalankan ulang.
    $this->seed(DemoSeeder::class);
    expect(StockOpname::count())->toBe(1)->and(PurchaseRequest::count())->toBe(3)->and(User::count())->toBe(4);
});

it('mengganti cabang fiktif dari data demo lama di tempat, tanpa cabang ganda', function () {
    $old = Branch::create(['code' => 'KMG', 'name' => 'Senyum Dental — Kemang', 'address' => 'Jl. Contoh', 'phone' => '021-0000000']);

    $this->seed(DemoSeeder::class);

    expect($old->refresh())->code->toBe('PDR')->name->toBe('Pondok Ranggon')->phone->toBeNull()
        ->and(Branch::pluck('code')->sort()->values()->all())->toBe(['CTH', 'PDR']);
});

it('menolak berjalan di produksi', function () {
    app()->detectEnvironment(fn () => 'production');

    // Panggil langsung (db:seed di produksi meminta konfirmasi interaktif).
    app(DemoSeeder::class)->run();
})->throws(RuntimeException::class);
