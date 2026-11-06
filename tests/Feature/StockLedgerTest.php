<?php

use App\Enums\InvoiceItemType;
use App\Enums\ItemType;
use App\Enums\StockMovementStatus;
use App\Enums\StockMovementType;
use App\Enums\StockOpnameStatus;
use App\Enums\StockReason;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Patient;
use App\Models\StockMovement;
use App\Models\Treatment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\BillingApprovals;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Clinic\MedicalRecordSigning;
use App\Services\Stock\ExpiryDate;
use App\Services\Stock\StockAlerts;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockOpnames;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00'));
    $this->branch = Branch::factory()->create(['code' => 'KMG']);
    $this->ampul = Unit::create(['name' => 'ampul']);
    $this->kotak = Unit::create(['name' => 'kotak']);
    $this->lido = Item::create(['sku' => 'LIDO', 'name' => 'Lidokain 2%', 'type' => ItemType::Medicine, 'base_unit_id' => $this->ampul->id, 'min_stock' => 30]);
    $this->lido->units()->create(['unit_id' => $this->kotak->id, 'factor_to_base' => 50]);
    $this->admin = User::factory()->withMfa()->create();
    $this->admin->assignRole('admin');
    $this->perawat = User::factory()->withMfa()->create();
    $this->perawat->assignRole('perawat');
    $this->actingAs($this->perawat);
});

afterEach(fn () => Carbon::setTestNow());

function receiveLido(array $lines): StockMovement
{
    return StockLedger::receive(test()->branch->id, $lines, null, 'FKT-1', null, null, test()->perawat);
}

it('mencatat penerimaan dengan konversi satuan dan harga pokok per satuan dasar', function () {
    $movement = receiveLido([
        ['item_id' => $this->lido->id, 'unit_id' => $this->kotak->id, 'qty' => 2, 'unit_price' => 490_000, 'batch_number' => 'LD-0612', 'expired_at' => '11/2026'],
    ]);

    $batch = ItemBatch::first();
    expect($movement)->number->toBe('PNR-KMG-2611-0001')->status->toBe(StockMovementStatus::Posted)->total_cost->toBe(980_000)
        ->and((float) $batch->qty_on_hand)->toBe(100.0)
        ->and($batch->unit_cost)->toBe(9_800)
        ->and($batch->expired_at->toDateString())->toBe('2026-11-30');
});

it('mengeluarkan stok dengan FEFO, melewati batch kedaluwarsa, dan mencatat kekurangan sebagai minus', function () {
    receiveLido([
        ['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 10, 'unit_price' => 10_000, 'batch_number' => 'B-LATE', 'expired_at' => '2027-06-30'],
        ['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 5, 'unit_price' => 9_000, 'batch_number' => 'B-SOON', 'expired_at' => '2026-12-31'],
        ['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 4, 'unit_price' => 8_000, 'batch_number' => 'B-OLD', 'expired_at' => '2026-10-31'],
    ]);

    $usage = StockLedger::issue($this->branch->id, StockMovementType::Usage, [['item_id' => $this->lido->id, 'qty' => 8]]);
    $lines = $usage->lines()->with('batch')->get();

    expect($lines->map(fn ($l) => [$l->batch->batch_number, (float) $l->qty])->all())->toBe([['B-SOON', -5.0], ['B-LATE', -3.0]])
        ->and($usage->total_cost)->toBe(-(5 * 9_000 + 3 * 10_000))
        ->and(StockLedger::available($this->lido->id, $this->branch->id))->toBe(7.0);

    StockLedger::issue($this->branch->id, StockMovementType::Usage, [['item_id' => $this->lido->id, 'qty' => 10]]);

    $unbatched = ItemBatch::where('is_unbatched', true)->first();
    expect((float) $unbatched->qty_on_hand)->toBe(-3.0)
        ->and(StockAlerts::negative($this->branch->id))->toHaveCount(1)
        ->and(StockAlerts::belowMinimum($this->branch->id)->pluck('id')->all())->toBe([$this->lido->id])
        ->and(StockAlerts::expiring($this->branch->id)->pluck('batch_number')->all())->toBe(['B-OLD']);
});

it('memotong bahan sesuai BOM saat rekam medis ditandatangani, sekali saja, dan menyimpan HPP di tagihan', function () {
    $this->seed(ReferenceDataSeeder::class);
    receiveLido([['item_id' => $this->lido->id, 'unit_id' => $this->kotak->id, 'qty' => 1, 'unit_price' => 500_000, 'batch_number' => 'L1', 'expired_at' => '2027-01-31']]);
    $extraction = Treatment::factory()->create(['base_price' => 300_000, 'icd9cm_code' => null]);
    $extraction->materials()->create(['item_id' => $this->lido->id, 'qty' => 2, 'unit_id' => $this->ampul->id]);

    $visit = Visit::factory()->inTreatment()->create(['branch_id' => $this->branch->id]);
    $procedure = $visit->procedures()->create(['treatment_id' => $extraction->id, 'operator_id' => $visit->practitioner_id, 'tooth_number' => '38']);
    $record = $visit->ensureMedicalRecord();
    $record->update(['subjective' => 'Gigi bungsu sakit']);
    $record->diagnoses()->create(['icd10_code' => 'K02.1']);

    $doctor = loginAs('dokter');
    MedicalRecordSigning::sign($visit->fresh(), $doctor);
    StockLedger::usageForVisit($visit->fresh(), $doctor);

    $usage = StockMovement::where('type', StockMovementType::Usage)->get();
    expect($usage)->toHaveCount(1)
        ->and((float) $usage->first()->lines()->sum('qty'))->toBe(-2.0)
        ->and($usage->first()->lines()->first()->visit_procedure_id)->toBe($procedure->id)
        ->and(StockAlerts::estimatedMaterialCost($extraction, $this->branch->id))->toBe(20_000);

    $invoice = InvoiceBuilder::issue(InvoiceBuilder::forVisit($visit->fresh()));
    expect($invoice->items()->first()->material_cost)->toBe(20_000);
});

it('menjual barang retail saat tagihan terbit dan mengembalikannya saat tagihan di-void', function () {
    $pcs = Unit::create(['name' => 'pcs']);
    $brush = Item::create(['sku' => 'SKT', 'name' => 'Sikat gigi ortho', 'type' => ItemType::Retail, 'base_unit_id' => $pcs->id, 'sell_price' => 35_000]);
    StockLedger::receive($this->branch->id, [['item_id' => $brush->id, 'unit_id' => $pcs->id, 'qty' => 10, 'unit_price' => 20_000]]);

    $invoice = InvoiceBuilder::blank(Patient::factory()->create(), $this->branch->id);
    $invoice->items()->create(['item_type' => InvoiceItemType::Item, 'item_id' => $brush->id, 'unit_id' => $pcs->id, 'description' => 'Sikat gigi ortho', 'qty' => 2, 'unit_price' => 35_000]);
    InvoiceBuilder::issue($invoice);

    expect(StockLedger::available($brush->id, $this->branch->id))->toBe(8.0);

    $owner = User::factory()->withMfa()->create();
    $owner->assignRole('owner');
    BillingApprovals::requestInvoiceVoid($invoice->fresh(), 'Salah input barang', $this->perawat);
    BillingApprovals::decideInvoiceVoid($invoice->fresh(), true, $owner);

    expect(StockLedger::available($brush->id, $this->branch->id))->toBe(10.0)
        ->and(StockMovement::where('type', StockMovementType::Reversal)->count())->toBe(1);
});

it('memusnahkan batch kedaluwarsa hanya setelah disetujui orang lain yang berhak', function () {
    receiveLido([['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 6, 'unit_price' => 9_000, 'batch_number' => 'EXP', 'expired_at' => '2026-10-31']]);
    $batch = ItemBatch::first();

    $disposal = StockLedger::requestDisposal($this->branch->id, [['item_batch_id' => $batch->id, 'qty' => 6]], StockReason::Expired, 'Pemusnahan bulanan', $this->perawat);

    expect($disposal->status)->toBe(StockMovementStatus::Pending)
        ->and((float) $batch->fresh()->qty_on_hand)->toBe(6.0)
        ->and(fn () => StockLedger::approve($disposal, $this->perawat))->toThrow(InvalidArgumentException::class);

    StockLedger::approve($disposal->fresh(), $this->admin);

    expect($disposal->fresh()->status)->toBe(StockMovementStatus::Posted)
        ->and((float) $batch->fresh()->qty_on_hand)->toBe(0.0)
        ->and(fn () => $disposal->fresh()->update(['notes' => 'ubah']))->toThrow(LogicException::class);
});

it('stok opname pertama menjadi saldo awal; selisih berikutnya dicatat sebagai koreksi', function () {
    $opname = StockOpnames::start($this->branch->id, $this->perawat);
    $line = $opname->lines()->where('item_id', $this->lido->id)->first();
    $line->update(['counted_qty' => 40, 'batch_number' => 'OPEN-1', 'expired_at' => '2027-03-31', 'unit_cost' => 9_500]);

    StockOpnames::submit($opname, $this->perawat);
    expect(fn () => StockOpnames::approve($opname->fresh(), $this->perawat))->toThrow(InvalidArgumentException::class);
    StockOpnames::approve($opname->fresh(), $this->admin);

    $batch = ItemBatch::where('batch_number', 'OPEN-1')->first();
    expect($opname->fresh()->status)->toBe(StockOpnameStatus::Approved)
        ->and((float) $batch->qty_on_hand)->toBe(40.0)
        ->and($batch->unit_cost)->toBe(9_500);

    StockLedger::issue($this->branch->id, StockMovementType::Usage, [['item_id' => $this->lido->id, 'qty' => 5]]);

    $second = StockOpnames::start($this->branch->id, $this->perawat);
    expect(fn () => StockOpnames::submit($second, $this->perawat))->toThrow(InvalidArgumentException::class, 'belum dihitung');

    $second->lines()->where('item_batch_id', $batch->id)->first()->update(['counted_qty' => 33]);
    StockOpnames::submit($second, $this->perawat);
    StockOpnames::approve($second->fresh(), $this->admin);

    expect((float) $batch->fresh()->qty_on_hand)->toBe(33.0)
        ->and($second->fresh()->adjustment->lines()->first())->qty->toBe('-2.000')->line_cost->toBe(-19_000);
});

it('memindahkan stok antar cabang dengan batch dan harga pokok yang sama', function () {
    $depok = Branch::factory()->create(['code' => 'DPK']);
    receiveLido([['item_id' => $this->lido->id, 'unit_id' => $this->ampul->id, 'qty' => 20, 'unit_price' => 9_800, 'batch_number' => 'TR-1', 'expired_at' => '2027-05-31']]);

    [$out, $in] = StockLedger::transfer($this->branch->id, $depok->id, [['item_id' => $this->lido->id, 'qty' => 12]]);

    $target = ItemBatch::where('branch_id', $depok->id)->first();
    expect($out->number)->toStartWith('TRF-KMG-')->and($in->number)->toStartWith('TRF-DPK-')
        ->and(StockLedger::available($this->lido->id, $this->branch->id))->toBe(8.0)
        ->and($target)->batch_number->toBe('TR-1')->unit_cost->toBe(9_800)
        ->and((float) $target->qty_on_hand)->toBe(12.0)
        ->and(fn () => StockLedger::transfer($this->branch->id, $depok->id, [['item_id' => $this->lido->id, 'qty' => 9]]))->toThrow(InvalidArgumentException::class);
});

it('membaca tanggal kedaluwarsa dari kemasan', function (string $input, string $expected) {
    expect(ExpiryDate::parse($input)->toDateString())->toBe($expected);
})->with([
    ['08/2027', '2027-08-31'],
    ['2/2028', '2028-02-29'],
    ['2027-06', '2027-06-30'],
    ['14/02/2024', '2024-02-14'],
    ['2026-12-01', '2026-12-01'],
]);

it('menolak tanggal kedaluwarsa yang tidak ada di kalender', function (string $input) {
    expect(fn () => ExpiryDate::parse($input))->toThrow(InvalidArgumentException::class);
})->with(['13/2027', '31/31/2027', '30/02/2027', '2027-13', '2027-02-30', 'Agustus 2027']);
