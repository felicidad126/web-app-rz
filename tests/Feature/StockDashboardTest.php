<?php

use App\Enums\ItemType;
use App\Enums\StockMovementType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\PurchasingFlow;
use App\Filament\Widgets\StockFlowChart;
use App\Filament\Widgets\StockValueByCategory;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Purchasing\Purchasing;
use App\Services\Stock\StockDashboard;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-12 09:00')); // Kamis; minggu berjalan mulai Senin 9 Nov
    $this->branch = Branch::factory()->create(['code' => 'KMG']);
    $this->pcs = Unit::create(['name' => 'pcs']);
    $this->box = Unit::create(['name' => 'box']);
    $this->disposable = ItemCategory::create(['name' => 'Disposable']);
    $this->obat = ItemCategory::create(['name' => 'Obat']);
    $this->glove = Item::create(['sku' => 'GLV-M', 'name' => 'Sarung tangan M', 'type' => ItemType::Material, 'base_unit_id' => $this->pcs->id, 'min_stock' => 100, 'item_category_id' => $this->disposable->id]);
    $this->glove->units()->create(['unit_id' => $this->box->id, 'factor_to_base' => 100]);
    $this->lido = Item::create(['sku' => 'LIDO', 'name' => 'Lidokain', 'type' => ItemType::Material, 'base_unit_id' => $this->pcs->id, 'min_stock' => 0, 'item_category_id' => $this->obat->id]);
    $this->supplier = Supplier::create(['name' => 'PT Dental Sehat']);

    $this->perawat = User::factory()->withMfa()->create();
    $this->perawat->assignRole('perawat');
    $this->admin = User::factory()->withMfa()->create();
    $this->admin->assignRole('admin');
    $this->owner = User::factory()->withMfa()->create();
    $this->owner->assignRole('owner');
    foreach ([$this->perawat, $this->admin, $this->owner] as $user) {
        $user->branches()->sync([$this->branch->id]);
    }
});

it('menghitung pemakaian & penerimaan per minggu dari mutasi tercatat, tanpa mutasi yang dibatalkan', function () {
    $b = $this->branch->id;
    StockLedger::receive($b, [['item_id' => $this->glove->id, 'unit_id' => $this->pcs->id, 'qty' => 300, 'unit_price' => 500]], null, null, '2026-10-28', null, $this->perawat);
    StockLedger::issue($b, StockMovementType::Usage, [['item_id' => $this->glove->id, 'qty' => 40]], null, 'Pemakaian', $this->perawat, '2026-11-03');
    StockLedger::issue($b, StockMovementType::Usage, [['item_id' => $this->glove->id, 'qty' => 10]], null, 'Pemakaian', $this->perawat, '2026-11-10');
    $wrong = StockLedger::issue($b, StockMovementType::Usage, [['item_id' => $this->glove->id, 'qty' => 99]], null, 'Salah catat', $this->perawat, '2026-11-11');
    StockLedger::cancel($wrong, 'Salah input', $this->admin);

    $weeks = StockDashboard::flow($b);

    expect($weeks)->toHaveCount(8)
        ->and($weeks[7])->label->toBe('9 Nov')->current->toBeTrue()->usage->toBe(5_000)->receipt->toBe(0)
        ->and($weeks[6])->label->toBe('2 Nov')->usage->toBe(20_000)
        ->and($weeks[5])->label->toBe('26 Okt')->receipt->toBe(150_000)->usage->toBe(0)
        ->and($weeks[0]['label'])->toBe('21 Sep');

    $months = StockDashboard::flow($b, 'months');

    expect($months)->toHaveCount(6)
        ->and($months[5])->label->toBe('Nov 2026')->usage->toBe(25_000)
        ->and($months[4])->label->toBe('Okt 2026')->receipt->toBe(150_000);
});

it('nilai per kategori berjumlah sama dengan nilai persediaan; lebih dari 7 kategori digabung "Lainnya"', function () {
    $b = $this->branch->id;
    StockLedger::receive($b, [
        ['item_id' => $this->glove->id, 'unit_id' => $this->pcs->id, 'qty' => 200, 'unit_price' => 600],
        ['item_id' => $this->lido->id, 'unit_id' => $this->pcs->id, 'qty' => 10, 'unit_price' => 9_800, 'batch_number' => 'LD-1', 'expired_at' => '08/2027'],
    ], null, null, null, null, $this->perawat);

    expect(StockDashboard::valueByCategory($b))->toBe([
        ['label' => 'Disposable', 'value' => 120_000],
        ['label' => 'Obat', 'value' => 98_000],
    ])->and(StockDashboard::inventoryValue($b))->toBe(218_000)
        ->and(StockDashboard::itemsInStock($b))->toBe(2);

    foreach (range(1, 7) as $i) {
        $category = ItemCategory::create(['name' => "Kategori $i"]);
        $item = Item::create(['sku' => "X$i", 'name' => "Barang $i", 'type' => ItemType::Material, 'base_unit_id' => $this->pcs->id, 'item_category_id' => $category->id]);
        StockLedger::receive($b, [['item_id' => $item->id, 'unit_id' => $this->pcs->id, 'qty' => 1, 'unit_price' => 1_000 * $i]], null, null, null, null, $this->perawat);
    }

    $rows = StockDashboard::valueByCategory($b);

    expect($rows)->toHaveCount(7)
        ->and(end($rows))->toBe(['label' => 'Lainnya', 'value' => 1_000 + 2_000 + 3_000])
        ->and(array_sum(array_column($rows, 'value')))->toBe(StockDashboard::inventoryValue($b));
});

it('saldo kas pembelian per minggu dan uang keluar 30 hari', function () {
    $b = $this->branch->id;
    Purchasing::deposit($b, 2_000_000, 'Setoran awal', $this->owner, '2026-10-20');
    Purchasing::expense($b, 20_000, 'Ongkos kirim', $this->admin, '2026-11-03');
    Purchasing::expense($b, 30_000, 'Materai', $this->admin, '2026-11-10');

    expect(StockDashboard::cashBalanceWeekly($b))->toBe([0, 0, 0, 0, 2_000_000, 2_000_000, 1_980_000, 1_950_000])
        ->and(StockDashboard::cashOut($b))->toBe(50_000);
});

it('alur pembelian dan daftar perlu tindakan menyesuaikan peran', function () {
    $b = $this->branch->id;
    StockLedger::receive($b, [
        ['item_id' => $this->glove->id, 'unit_id' => $this->pcs->id, 'qty' => 50, 'unit_price' => 500],
        ['item_id' => $this->lido->id, 'unit_id' => $this->pcs->id, 'qty' => 5, 'unit_price' => 9_800, 'batch_number' => 'LD-OLD', 'expired_at' => '10/2026'],
        ['item_id' => $this->lido->id, 'unit_id' => $this->pcs->id, 'qty' => 5, 'unit_price' => 9_800, 'batch_number' => 'LD-NEW', 'expired_at' => '12/2026'],
    ], null, null, null, null, $this->perawat);

    $pending = Purchasing::requestItem($b, $this->glove->id, 1, $this->box->id, 'Stok tinggal setengah box', $this->perawat);
    $order = Purchasing::createOrder($b, $this->supplier->id, '2026-11-02', [
        ['item_id' => $this->lido->id, 'unit_id' => $this->pcs->id, 'qty' => 10, 'unit_price' => 9_800],
    ], $this->admin, expectedAt: '2026-11-05');

    expect(StockDashboard::purchasing($b))
        ->pending->toBe(1)->approved->toBe(0)->open->toBe(1)->late->toBe(1)
        ->and(StockDashboard::purchasing($b)['pendingExample']?->is($pending))->toBeTrue();

    $owner = StockDashboard::actionItems($b, $this->owner);
    expect($owner->pluck('kind')->all())->toBe(['expired', 'approve-request', 'late-order', 'below-minimum', 'expiring'])
        ->and($owner->firstWhere('kind', 'below-minimum'))->meta->toContain('sudah diminta')->code->toBe($pending->number)
        ->and($owner->firstWhere('kind', 'late-order'))->code->toBe($order->number)->tone->toBe('warning');

    $perawat = StockDashboard::actionItems($b, $this->perawat);
    expect($perawat->pluck('kind')->all())->toBe(['expired', 'late-order', 'below-minimum', 'expiring'])
        ->and($perawat->firstWhere('kind', 'expired'))->tone->toBe('danger')->badge->toBe('Lewat ED');
});

it('permintaan untuk barang yang sudah diarsipkan tetap tampil tanpa error', function () {
    $b = $this->branch->id;
    Purchasing::requestItem($b, $this->lido->id, 2, $this->pcs->id, 'Untuk tindakan besok', $this->perawat);
    $this->lido->delete();

    expect(StockDashboard::actionItems($b, $this->owner)->firstWhere('kind', 'approve-request'))->title->toBe('Lidokain')
        ->and(StockDashboard::purchasing($b)['pendingExample']?->item?->name)->toBe('Lidokain');
});

it('halaman Dashboard: tombol utama dan kartu kas menyesuaikan peran', function () {
    $b = $this->branch->id;
    Purchasing::deposit($b, 500_000, 'Setoran awal', $this->owner);
    StockLedger::receive($b, [['item_id' => $this->glove->id, 'unit_id' => $this->pcs->id, 'qty' => 20, 'unit_price' => 500]], null, null, null, null, $this->perawat);

    $this->actingAs($this->perawat);
    Livewire::test(Dashboard::class)->assertOk()
        ->assertSee('Selamat datang')
        ->assertActionVisible('createRequest')
        ->assertActionHidden('approvals');
    Livewire::test(DashboardStats::class)->assertSee('Stok minus')->assertDontSee('Saldo kas pembelian');
    Livewire::test(PurchasingFlow::class)->assertSee('Alur pembelian')->assertDontSee('Saldo kas pembelian');
    Livewire::test(StockFlowChart::class)->assertOk();
    Livewire::test(StockValueByCategory::class)->assertSee('Disposable')->assertSee('10.000');

    Purchasing::requestItem($b, $this->glove->id, 1, $this->box->id, 'Stok menipis', $this->perawat);

    $this->actingAs($this->owner);
    Livewire::test(Dashboard::class)->assertOk()
        ->assertActionVisible('approvals')
        ->assertSee('Tinjau persetujuan · 1')
        ->assertActionHidden('createRequest');
    Livewire::test(DashboardStats::class)->assertSee('Saldo kas pembelian')->assertSee('Rp 500.000')->assertDontSee('Stok minus');
});
