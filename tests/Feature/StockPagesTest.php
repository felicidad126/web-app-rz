<?php

use App\Enums\ItemType;
use App\Enums\LabOrderStatus;
use App\Enums\LabWorkType;
use App\Enums\StockMovementStatus;
use App\Enums\StockMovementType;
use App\Enums\StockOpnameStatus;
use App\Enums\StockReason;
use App\Filament\Clusters\Stock\Pages\StockOverview;
use App\Filament\Clusters\Stock\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Clusters\Stock\Resources\StockMovements\Pages\ViewStockMovement;
use App\Filament\Clusters\Stock\Resources\StockOpnames\Pages\CountStockOpname;
use App\Filament\Clusters\Stock\Resources\StockOpnames\Pages\ListStockOpnames;
use App\Filament\Clusters\Stock\Resources\StockOpnames\Pages\ViewStockOpname;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\RelationManagers\BatchesRelationManager;
use App\Filament\Resources\Items\RelationManagers\StockCardRelationManager;
use App\Filament\Resources\LabOrders\Pages\CreateLabOrder;
use App\Filament\Resources\LabOrders\Pages\ListLabOrders;
use App\Filament\Resources\LabOrders\Pages\ViewLabOrder;
use App\Filament\Resources\Treatments\Pages\EditTreatment;
use App\Filament\Resources\Treatments\RelationManagers\MaterialsRelationManager;
use App\Filament\Widgets\StockAlertsWidget;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\Treatment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visit;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00'));
    $this->branch = Branch::factory()->create(['code' => 'KMG', 'name' => 'Kemang']);
    $this->ampul = Unit::create(['name' => 'ampul']);
    $this->kotak = Unit::create(['name' => 'kotak']);
    $this->lido = Item::create(['sku' => 'LIDO', 'name' => 'Lidokain 2%', 'type' => ItemType::Medicine, 'base_unit_id' => $this->ampul->id, 'min_stock' => 30]);
    $this->lido->units()->create(['unit_id' => $this->kotak->id, 'factor_to_base' => 50]);
    $this->supplier = Supplier::create(['name' => 'PT Medika Jaya']);
});

afterEach(fn () => Carbon::setTestNow());

function stockUser(string $role): User
{
    $user = User::factory()->withMfa()->create();
    $user->assignRole($role);
    $user->branches()->sync([test()->branch->id]);
    test()->actingAs($user);

    return $user;
}

it('perawat mencatat penerimaan dari halaman Stok dengan konversi satuan dan ED bulan/tahun', function () {
    stockUser('perawat');

    Livewire::test(StockOverview::class)
        ->callAction('receive', [
            'supplier_id' => $this->supplier->id,
            'supplier_invoice_no' => 'FKT-778',
            'movement_date' => '2026-11-05',
            'lines' => [
                ['item_id' => $this->lido->id, 'unit_id' => $this->kotak->id, 'qty' => 2, 'unit_price' => 490_000, 'batch_number' => 'LD-0612', 'expired_at' => '08/2027'],
            ],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $batch = ItemBatch::sole();
    expect((float) $batch->qty_on_hand)->toBe(100.0)
        ->and($batch->unit_cost)->toBe(9_800)
        ->and($batch->expired_at->toDateString())->toBe('2027-08-31')
        ->and(StockMovement::sole())->supplier_invoice_no->toBe('FKT-778')->total_cost->toBe(980_000);

    Livewire::test(StockOverview::class)->assertOk()->assertSee('Lidokain 2%')->assertSee('LD-0612');
});

it('menolak ED yang tidak bisa dibaca saat penerimaan', function () {
    stockUser('perawat');

    Livewire::test(StockOverview::class)
        ->callAction('receive', [
            'movement_date' => '2026-11-05',
            'lines' => [['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 10, 'unit_price' => 9_000, 'batch_number' => 'X', 'expired_at' => '31/31/2027']],
        ])
        ->assertHasActionErrors();

    expect(StockMovement::count())->toBe(0);
});

it('opname: perawat menghitung buta lalu mengajukan, admin menyetujui, stok sistem = hasil hitung', function () {
    $perawat = stockUser('perawat');
    StockLedger::receive($this->branch->id, [
        ['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 50, 'unit_price' => 9_800, 'batch_number' => 'LD-1', 'expired_at' => '08/2027'],
    ], null, null, null, null, $perawat);

    Livewire::test(ListStockOpnames::class)->callAction('startOpname');
    $opname = StockOpname::sole();
    $line = $opname->lines()->whereNotNull('item_batch_id')->sole();

    // Hitung buta: saldo sistem (50) tidak tampil di lembar hitung.
    Livewire::test(CountStockOpname::class, ['record' => $opname->getRouteKey()])
        ->assertOk()
        ->assertDontSee('Saldo sistem')
        ->set("data.lines.record-{$line->id}.counted_qty", 46)
        ->callAction('submit')
        ->assertNotified('Opname diajukan');

    expect($opname->fresh()->status)->toBe(StockOpnameStatus::Pending);

    // Pengaju tidak bisa menyetujui (perawat juga tidak punya izin).
    Livewire::test(ViewStockOpname::class, ['record' => $opname->getRouteKey()])->assertActionHidden('approve');

    stockUser('admin');
    Livewire::test(ViewStockOpname::class, ['record' => $opname->getRouteKey()])
        ->assertSee('46')
        ->callAction('approve')
        ->assertNotified('Opname disetujui');

    expect($opname->fresh()->status)->toBe(StockOpnameStatus::Approved)
        ->and((float) ItemBatch::where('batch_number', 'LD-1')->value('qty_on_hand'))->toBe(46.0)
        ->and(StockMovement::where('type', StockMovementType::Adjustment)->sole()->total_cost)->toBe(-39_200);
});

it('pemusnahan: perawat mengajukan, admin menyetujui dari halaman mutasi, berita acara bisa dicetak', function () {
    $perawat = stockUser('perawat');
    StockLedger::receive($this->branch->id, [
        ['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 20, 'unit_price' => 9_800, 'batch_number' => 'OLD', 'expired_at' => '09/2026'],
    ], null, null, '2026-01-10', null, $perawat);
    $batch = ItemBatch::sole();

    // Batch kedaluwarsa otomatis terisi di form pemusnahan.
    Livewire::test(ListStockMovements::class)
        ->mountAction('disposal')
        ->assertSchemaStateSet(['reason' => StockReason::Expired], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $disposal = StockMovement::where('type', StockMovementType::Disposal)->sole();
    expect($disposal->status)->toBe(StockMovementStatus::Pending)
        ->and((float) $batch->fresh()->qty_on_hand)->toBe(20.0);

    Livewire::test(ViewStockMovement::class, ['record' => $disposal->getRouteKey()])->assertActionHidden('approve');

    stockUser('admin');
    Livewire::test(ViewStockMovement::class, ['record' => $disposal->getRouteKey()])
        ->callAction('approve')
        ->assertNotified('Disetujui dan tercatat');

    expect($disposal->fresh()->status)->toBe(StockMovementStatus::Posted)
        ->and((float) $batch->fresh()->qty_on_hand)->toBe(0.0);

    $pdf = $this->get(route('stock.movement-pdf', $disposal));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');
});

it('berita acara hanya untuk pengguna dengan izin stok di cabangnya', function () {
    $perawat = stockUser('perawat');
    $movement = StockLedger::receive($this->branch->id, [
        ['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 5, 'unit_price' => 9_800, 'batch_number' => 'A', 'expired_at' => null],
    ], null, null, null, null, $perawat);

    stockUser('resepsionis');
    $this->get(route('stock.movement-pdf', $movement))->assertForbidden();

    $other = Branch::factory()->create(['code' => 'DPK']);
    $outsider = User::factory()->withMfa()->create();
    $outsider->assignRole('perawat');
    $outsider->branches()->sync([$other->id]);
    $this->actingAs($outsider)->get(route('stock.movement-pdf', $movement))->assertForbidden();
});

it('menampilkan batch & kartu stok di halaman barang, dan perkiraan HPP di BOM tindakan', function () {
    $perawat = stockUser('perawat');
    StockLedger::receive($this->branch->id, [
        ['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 50, 'unit_price' => 9_800, 'batch_number' => 'LD-1', 'expired_at' => '12/2026'],
    ], null, null, null, null, $perawat);
    StockLedger::issue($this->branch->id, StockMovementType::Usage, [['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 4]], null, 'Sterilisasi', $perawat);

    stockUser('admin');
    Livewire::test(EditItem::class, ['record' => $this->lido->getRouteKey()])->assertOk();
    Livewire::test(BatchesRelationManager::class, ['ownerRecord' => $this->lido, 'pageClass' => EditItem::class])
        ->assertOk()->assertSee('LD-1')->assertSee('46 ampul')->assertSee('56 hari lagi');
    Livewire::test(StockCardRelationManager::class, ['ownerRecord' => $this->lido, 'pageClass' => EditItem::class])
        ->assertOk()->assertSee('+50 ampul')->assertSee('−4 ampul')->assertSee('46 ampul');

    $treatment = Treatment::factory()->create(['name' => 'Pencabutan']);
    Livewire::test(MaterialsRelationManager::class, ['ownerRecord' => $treatment, 'pageClass' => EditTreatment::class])
        ->callTableAction('create', data: ['item_id' => $this->lido->id, 'qty' => 2, 'unit_id' => $this->ampul->id])
        ->assertHasNoTableActionErrors()
        ->assertSee('Rp 19.600');

    expect($treatment->materials()->sole())->item_id->toBe($this->lido->id);
});

it('dokter membuat order lab dari kunjungan; tahap maju sampai dipasang; terlambat dan janji pasang terlihat', function () {
    $lab = Supplier::create(['name' => 'Lab Prima Dental', 'is_lab' => true, 'phone' => '0812-1111-2222']);
    $doctor = Practitioner::factory()->create();
    $patient = Patient::factory()->create(['name' => 'Siti Aminah']);
    $visit = Visit::factory()->inTreatment()->create(['branch_id' => $this->branch->id, 'patient_id' => $patient->id, 'practitioner_id' => $doctor->id]);
    stockUser('dokter');

    $this->get('/order-lab/create?visit='.$visit->id)->assertOk();

    Livewire::withQueryParams(['visit' => $visit->id])
        ->test(CreateLabOrder::class)
        ->assertSchemaStateSet(['patient_id' => $patient->id, 'visit_id' => $visit->id, 'practitioner_id' => $doctor->id, 'branch_id' => $this->branch->id])
        ->fillForm([
            'supplier_id' => $lab->id,
            'work_type' => LabWorkType::Crown->value,
            'specification' => 'PFM, margin chamfer',
            'teeth' => '36',
            'shade' => 'A2',
            'cost' => 650_000,
            'due_at' => '2026-11-03',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = LabOrder::sole();
    expect($order)->number->toBe('LAB-2611-0001')->status->toBe(LabOrderStatus::Created)->cost->toBe(650_000)
        ->and($order->isLate())->toBeTrue();

    Appointment::factory()->create(['patient_id' => $patient->id, 'branch_id' => $this->branch->id, 'practitioner_id' => $doctor->id, 'start_at' => '2026-11-10 09:00', 'end_at' => '2026-11-10 09:30']);

    Livewire::test(ListLabOrders::class)
        ->assertOk()
        ->assertSee('1 terlambat')
        ->assertSee('terlambat 2 hari')
        ->assertSee('10/11/2026 09:00')
        ->callTableAction('advance', $order)
        ->assertNotified('Tahap: Dikirim ke lab');

    foreach ([LabOrderStatus::Received, LabOrderStatus::Checked, LabOrderStatus::Fitted] as $expected) {
        Livewire::test(ViewLabOrder::class, ['record' => $order->getRouteKey()])->callAction('advance');
        expect($order->fresh()->status)->toBe($expected);
    }

    $order->refresh();
    expect($order->sent_at)->not->toBeNull()->and($order->fitted_at)->not->toBeNull()
        ->and($order->isLate())->toBeFalse();

    Livewire::test(ViewLabOrder::class, ['record' => $order->getRouteKey()])
        ->assertOk()->assertActionHidden('advance')->assertActionHidden('cancel')->assertSee('Lab Prima Dental');
});

it('membatalkan order lab dengan alasan; biaya lab batal tidak ikut tagihan', function () {
    $lab = Supplier::create(['name' => 'Lab Prima Dental', 'is_lab' => true]);
    $doctor = Practitioner::factory()->create();
    $patient = Patient::factory()->create();
    stockUser('perawat');
    $order = LabOrder::create([
        'number' => 'LAB-2611-0009', 'branch_id' => $this->branch->id, 'patient_id' => $patient->id, 'practitioner_id' => $doctor->id,
        'supplier_id' => $lab->id, 'work_type' => LabWorkType::Retainer, 'specification' => 'Hawley', 'cost' => 400_000,
    ]);

    Livewire::test(ViewLabOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Pasien pindah kota'])
        ->assertNotified('Order lab dibatalkan');

    expect($order->fresh())->status->toBe(LabOrderStatus::Cancelled)->cancel_reason->toBe('Pasien pindah kota');
});

it('widget stok menampilkan peringatan minimum, kedaluwarsa, dan order lab terlambat', function () {
    $perawat = stockUser('perawat');
    StockLedger::receive($this->branch->id, [
        ['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 10, 'unit_price' => 9_800, 'batch_number' => 'LD-1', 'expired_at' => '12/2026'],
    ], null, null, null, null, $perawat);

    Livewire::test(StockAlertsWidget::class)
        ->assertOk()
        ->assertSee('Di bawah minimum')
        ->assertSee('Kedaluwarsa ≤ 60 hari')
        ->assertSee('Order lab terlambat');

    stockUser('resepsionis');
    expect(StockAlertsWidget::canView())->toBeFalse();
});

it('admin membatalkan penerimaan yang salah input; penerimaan yang sudah terpakai ditolak', function () {
    $perawat = stockUser('perawat');
    $wrong = StockLedger::receive($this->branch->id, [
        ['item_id' => $this->lido->id, 'unit_id' => $this->kotak->id, 'qty' => 10, 'unit_price' => 490_000, 'batch_number' => 'SALAH', 'expired_at' => '08/2027'],
    ], null, null, null, null, $perawat);
    $used = StockLedger::receive($this->branch->id, [
        ['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 10, 'unit_price' => 9_800, 'batch_number' => 'PAKAI', 'expired_at' => '01/2027'],
    ], null, null, null, null, $perawat);
    StockLedger::issue($this->branch->id, StockMovementType::Usage, [['item_id' => $this->lido->id, 'qty' => 3]], null, 'Pemakaian', $perawat);

    Livewire::test(ViewStockMovement::class, ['record' => $wrong->getRouteKey()])->assertActionHidden('cancel');

    stockUser('admin');
    Livewire::test(ViewStockMovement::class, ['record' => $wrong->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Salah ketik jumlah box'])
        ->assertNotified('Mutasi dibatalkan');

    expect((float) ItemBatch::where('batch_number', 'SALAH')->value('qty_on_hand'))->toBe(0.0)
        ->and(StockMovement::where('reverses_movement_id', $wrong->id)->sole()->type)->toBe(StockMovementType::Reversal);

    Livewire::test(ViewStockMovement::class, ['record' => $wrong->getRouteKey()])->assertActionHidden('cancel');

    Livewire::test(ViewStockMovement::class, ['record' => $used->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Coba batalkan'])
        ->assertNotified('Belum bisa diproses');

    expect((float) ItemBatch::where('batch_number', 'PAKAI')->value('qty_on_hand'))->toBe(7.0);
});
