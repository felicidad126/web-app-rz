<?php

use App\Enums\ItemType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Filament\Clusters\Stock\Pages\PurchaseCash;
use App\Filament\Clusters\Stock\Pages\StockOverview;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Filament\Clusters\Stock\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Clusters\Stock\Resources\StockMovements\Pages\ViewStockMovement;
use App\Filament\Widgets\StockAlertsWidget;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseCashEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Purchasing\Purchasing;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-12 09:00'));
    $this->branch = Branch::factory()->create(['code' => 'KMG', 'name' => 'Kemang']);
    $this->pcs = Unit::create(['name' => 'pcs']);
    $this->box = Unit::create(['name' => 'box']);
    $this->glove = Item::create(['sku' => 'GLV-M', 'name' => 'Sarung tangan M', 'type' => ItemType::Material, 'base_unit_id' => $this->pcs->id, 'min_stock' => 100]);
    $this->glove->units()->create(['unit_id' => $this->box->id, 'factor_to_base' => 100]);
    $this->supplier = Supplier::create(['name' => 'PT Dental Sehat']);
});

afterEach(fn () => Carbon::setTestNow());

function purchasingUser(string $role): User
{
    $user = User::factory()->withMfa()->create();
    $user->assignRole($role);
    $user->branches()->sync([test()->branch->id]);
    test()->actingAs($user);

    return $user;
}

it('perawat meminta beli dari tab Barang; barang ditandai sudah diminta', function () {
    purchasingUser('perawat');

    Livewire::test(StockOverview::class)
        ->assertSee('Di bawah minimum · pesan lagi')
        ->callTableAction('requestPurchase', $this->glove, ['item_id' => $this->glove->id, 'unit_id' => $this->box->id, 'qty' => 2, 'reason' => 'Tinggal sedikit'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Permintaan PMT-KMG-2611-0001 terkirim');

    expect(PurchaseRequest::sole())->status->toBe(PurchaseRequestStatus::Pending)->quantity->toEqual('2.000');

    Livewire::test(StockOverview::class)
        ->assertSee('sudah diminta PMT-KMG-2611-0001')
        ->assertTableActionHidden('requestPurchase', $this->glove);
});

it('admin menyetujui permintaan lalu membuat pesanan dari permintaan terpilih', function () {
    $perawat = purchasingUser('perawat');
    $request = Purchasing::requestItem($this->branch->id, $this->glove->id, 3, $this->box->id, null, $perawat);

    // Pengaju tidak melihat tombol Setujui.
    Livewire::test(ListPurchaseRequests::class)->assertTableActionHidden('approve', $request);

    purchasingUser('admin');
    Livewire::test(ListPurchaseRequests::class)
        ->assertCanSeeTableRecords([$request])
        ->callTableAction('approve', $request, ['note' => 'OK'])
        ->assertNotified('Permintaan PMT-KMG-2611-0001 disetujui');

    expect($request->refresh()->status)->toBe(PurchaseRequestStatus::Approved);

    Livewire::test(ListPurchaseRequests::class, ['activeTab' => 'disetujui'])
        ->assertCanSeeTableRecords([$request])
        ->callTableBulkAction('order', [$request])
        ->assertRedirect(PurchaseOrderResource::getUrl('create', ['permintaan' => (string) $request->id]));

    $page = Livewire::withQueryParams(['permintaan' => (string) $request->id])->test(CreatePurchaseOrder::class);
    $lines = $page->get('data.lines');
    expect($lines)->toHaveCount(1)
        ->and((int) collect($lines)->first()['purchase_request_id'])->toBe($request->id)
        ->and((float) collect($lines)->first()['qty'])->toBe(3.0);

    $page->fillForm(['supplier_id' => $this->supplier->id, 'expected_at' => '2026-11-14'])
        ->set('data.lines', collect($lines)->map(fn (array $line): array => [...$line, 'unit_price' => '85.000'])->all())
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Pesanan PSN-KMG-2611-0001 dibuat · Rp 255.000');

    $order = PurchaseOrder::sole();
    expect($order)->total->toBe(255_000)->supplier_id->toBe($this->supplier->id)
        ->and($request->refresh())->status->toBe(PurchaseRequestStatus::Ordered)->purchase_order_id->toBe($order->id);

    Livewire::test(ListPurchaseOrders::class)->assertCanSeeTableRecords([$order])->assertSee('Sarung tangan M');
});

it('perawat menerima sebagian dari halaman pesanan; admin membayar dari kas pembelian', function () {
    $admin = purchasingUser('admin');
    $order = Purchasing::createOrder($this->branch->id, $this->supplier->id, today(), [
        ['item_id' => $this->glove->id, 'unit_id' => $this->box->id, 'qty' => 3, 'unit_price' => 85_000],
    ], $admin, '2026-11-10');
    $line = $order->lines->first();

    purchasingUser('perawat');
    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee('Lewat 2 hari')
        ->assertActionHidden('pay')
        ->assertActionHidden('cancelOrder')
        ->callAction('receiveOrder', [
            'date' => '2026-11-12',
            'invoice_no' => 'INV-01',
            'lines' => [['line_id' => $line->id, 'qty' => 1, 'batch_number' => 'GL-01', 'expired_at' => '06/2028']],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Partial)
        ->and(StockLedger::available($this->glove->id, $this->branch->id))->toBe(100.0);

    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('receiveOrder', ['date' => '2026-11-12', 'lines' => [['line_id' => $line->id, 'qty' => 5]]])
        ->assertNotified('Belum tersimpan');

    purchasingUser('admin');
    Purchasing::deposit($this->branch->id, 300_000, 'Setoran owner', $admin);

    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee('Diterima sebagian')
        ->assertActionHidden('cancelOrder')
        ->callAction('pay', ['amount' => '255.000', 'date' => '2026-11-12'])
        ->assertHasNoActionErrors()
        ->assertNotified('Pembayaran Rp 255.000 tercatat');

    expect($order->refresh()->paid_amount)->toBe(255_000)
        ->and(Purchasing::balance($this->branch->id))->toBe(45_000);

    // Penerimaan dari pesanan dibatalkan lewat halaman mutasi: jumlah diterima di pesanan ikut kembali.
    Livewire::test(ViewStockMovement::class, ['record' => $order->receipts()->first()->getRouteKey()])
        ->assertSee($order->number)
        ->callAction('cancel', ['reason' => 'Salah input jumlah'])
        ->assertNotified('Mutasi dibatalkan');

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Ordered)
        ->and(StockLedger::available($this->glove->id, $this->branch->id))->toBe(0.0);
});

it('owner mengelola kas pembelian: setor, pengeluaran, koreksi, saldo berjalan', function () {
    purchasingUser('owner');

    Livewire::test(PurchaseCash::class)
        ->callAction('deposit', ['amount' => '500.000', 'date' => '2026-11-12', 'description' => 'Setoran minggu ke-2'])
        ->assertNotified('Setoran Rp 500.000 tercatat');

    // Saldo tidak cukup: modal tetap terbuka dengan pesan.
    Livewire::test(PurchaseCash::class)
        ->callAction('expense', ['amount' => '600.000', 'date' => '2026-11-12', 'description' => 'Ongkir'])
        ->assertNotified('Belum tersimpan');

    Livewire::test(PurchaseCash::class)
        ->callAction('expense', ['amount' => '25.000', 'date' => '2026-11-12', 'description' => 'Ongkos kirim'])
        ->assertNotified('Pengeluaran Rp 25.000 tercatat');

    $expense = PurchaseCashEntry::where('amount', -25_000)->sole();

    Livewire::test(PurchaseCash::class)
        ->assertSee('saldo Rp 475.000')
        ->assertSee('475.000')
        ->callTableAction('reverseCash', $expense, ['reason' => 'Ongkir ditanggung supplier'])
        ->assertNotified('Koreksi tercatat');

    expect(Purchasing::balance($this->branch->id))->toBe(500_000);

    Livewire::test(PurchaseCash::class)
        ->assertSee('Sudah dikoreksi')
        ->assertTableActionHidden('reverseCash', $expense);
});

it('memuat semua tab mutasi, permintaan, dan pesanan', function () {
    purchasingUser('admin');

    foreach (['semua', 'penerimaan', 'keluar', 'transfer', 'koreksi', 'persetujuan'] as $tab) {
        Livewire::test(ListStockMovements::class, ['activeTab' => $tab])->assertOk();
    }

    foreach (['terbuka', 'menunggu', 'disetujui', 'selesai', 'semua'] as $tab) {
        Livewire::test(ListPurchaseRequests::class, ['activeTab' => $tab])->assertOk();
    }

    foreach (['belum', 'diterima', 'batal', 'semua'] as $tab) {
        Livewire::test(ListPurchaseOrders::class, ['activeTab' => $tab])->assertOk();
    }
});

it('widget stok menampilkan permintaan menunggu untuk penyetuju', function () {
    $perawat = purchasingUser('perawat');
    Purchasing::requestItem($this->branch->id, $this->glove->id, 1, $this->box->id, null, $perawat);

    purchasingUser('admin');
    Livewire::test(StockAlertsWidget::class)
        ->assertSee('Pembelian')
        ->assertSee('1 permintaan menunggu persetujuan');
});
