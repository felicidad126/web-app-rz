<?php

use App\Enums\ItemType;
use App\Enums\PurchaseCashType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\StockMovementType;
use App\Filament\Imports\ItemImporter;
use App\Filament\Imports\SupplierImporter;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\PurchaseCashEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Purchasing\Purchasing;
use App\Services\Stock\StockLedger;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-12 09:00'));
    $this->branch = Branch::factory()->create(['code' => 'KMG']);
    $this->pcs = Unit::create(['name' => 'pcs']);
    $this->box = Unit::create(['name' => 'box']);
    $this->glove = Item::create(['sku' => 'GLV-M', 'name' => 'Sarung tangan M', 'type' => ItemType::Material, 'base_unit_id' => $this->pcs->id, 'min_stock' => 100]);
    $this->glove->units()->create(['unit_id' => $this->box->id, 'factor_to_base' => 100]);
    $this->masker = Item::create(['sku' => 'MSK', 'name' => 'Masker', 'type' => ItemType::Material, 'base_unit_id' => $this->pcs->id, 'min_stock' => 50]);
    $this->supplier = Supplier::create(['name' => 'PT Dental Sehat']);

    $this->perawat = User::factory()->withMfa()->create();
    $this->perawat->assignRole('perawat');
    $this->admin = User::factory()->withMfa()->create();
    $this->admin->assignRole('admin');
    $this->owner = User::factory()->withMfa()->create();
    $this->owner->assignRole('owner');
});

afterEach(fn () => Carbon::setTestNow());

function approvedGloveRequest(float $boxes = 2): PurchaseRequest
{
    $request = Purchasing::requestItem(test()->branch->id, test()->glove->id, $boxes, test()->box->id, 'Stok tinggal 1 box', test()->perawat);
    Purchasing::approve($request, test()->admin);

    return $request;
}

function gloveOrder(?int $requestId = null, float $boxes = 2, bool $payNow = false): PurchaseOrder
{
    return Purchasing::createOrder(test()->branch->id, test()->supplier->id, today(), [
        ['item_id' => test()->glove->id, 'unit_id' => test()->box->id, 'qty' => $boxes, 'unit_price' => 85_000, 'purchase_request_id' => $requestId],
    ], test()->admin, payNow: $payNow);
}

it('meminta barang lalu disetujui orang lain yang berhak (empat mata)', function () {
    $request = Purchasing::requestItem($this->branch->id, $this->glove->id, 2, $this->box->id, 'Stok tinggal 1 box', $this->perawat);

    expect($request)->number->toBe('PMT-KMG-2611-0001')->status->toBe(PurchaseRequestStatus::Pending)
        ->and((float) $request->base_quantity)->toBe(200.0);

    expect(fn () => Purchasing::approve($request, $this->perawat))->toThrow(InvalidArgumentException::class, 'tidak punya izin');

    $ownRequest = Purchasing::requestItem($this->branch->id, $this->masker->id, 50, $this->pcs->id, null, $this->admin);
    expect(fn () => Purchasing::approve($ownRequest, $this->admin))->toThrow(InvalidArgumentException::class, 'pengajunya sendiri');

    Purchasing::approve($request, $this->admin, 'Pesan ke supplier biasa');
    expect($request)->status->toBe(PurchaseRequestStatus::Approved)->decided_by->toBe($this->admin->id);

    Purchasing::reject($ownRequest, $this->owner, 'Masih ada 2 box di gudang');
    expect($ownRequest->refresh())->status->toBe(PurchaseRequestStatus::Rejected)->decision_note->toBe('Masih ada 2 box di gudang');

    expect(fn () => Purchasing::approve($ownRequest, $this->owner))->toThrow(InvalidArgumentException::class, 'sudah Ditolak');
});

it('membuat pesanan dari permintaan yang disetujui tanpa menambah stok', function () {
    $request = approvedGloveRequest();
    $pending = Purchasing::requestItem($this->branch->id, $this->masker->id, 50, $this->pcs->id, null, $this->perawat);

    expect(fn () => Purchasing::createOrder($this->branch->id, $this->supplier->id, today(), [
        ['item_id' => $this->masker->id, 'unit_id' => $this->pcs->id, 'qty' => 50, 'unit_price' => 1_000, 'purchase_request_id' => $pending->id],
    ], $this->admin))->toThrow(InvalidArgumentException::class, 'belum disetujui');

    $order = Purchasing::createOrder($this->branch->id, $this->supplier->id, today(), [
        ['item_id' => $this->glove->id, 'unit_id' => $this->box->id, 'qty' => 2, 'unit_price' => 85_000, 'purchase_request_id' => $request->id],
        ['item_id' => $this->masker->id, 'unit_id' => $this->pcs->id, 'qty' => 100, 'unit_price' => 1_500],
    ], $this->admin, '2026-11-15', 'Kirim pagi');

    expect($order)->number->toBe('PSN-KMG-2611-0001')->status->toBe(PurchaseOrderStatus::Ordered)
        ->total->toBe(320_000)->paid_amount->toBe(0)->created_by->toBe($this->admin->id)
        ->and($order->lines)->toHaveCount(2)
        ->and((float) $order->lines->first()->factor_to_base)->toBe(100.0)
        ->and($request->refresh())->status->toBe(PurchaseRequestStatus::Ordered)->purchase_order_id->toBe($order->id)
        ->and($pending->refresh()->status)->toBe(PurchaseRequestStatus::Pending)
        ->and(StockLedger::available($this->glove->id, $this->branch->id))->toBe(0.0);

    // Permintaan yang sudah dipesan tidak bisa dipesan dua kali.
    expect(fn () => gloveOrder($request->id))->toThrow(InvalidArgumentException::class, 'sudah dipesan');
});

it('menerima barang sebagian lalu sisanya; stok, batch, dan ED tercatat lewat buku stok', function () {
    $request = approvedGloveRequest(3);
    $order = gloveOrder($request->id, 3);
    $line = $order->lines->first();

    $first = Purchasing::receive($order, [
        ['line_id' => $line->id, 'qty' => 1, 'batch_number' => 'GL-01', 'expired_at' => '2028-06'],
    ], $this->perawat, invoiceNo: 'INV-778');

    expect($first)->type->toBe(StockMovementType::Receipt)->supplier_id->toBe($this->supplier->id)
        ->supplier_invoice_no->toBe('INV-778')->source_type->toBe($order->getMorphClass())->source_id->toBe($order->id)
        ->total_cost->toBe(85_000)
        ->and($order)->status->toBe(PurchaseOrderStatus::Partial)
        ->and(StockLedger::available($this->glove->id, $this->branch->id))->toBe(100.0)
        ->and(ItemBatch::where('batch_number', 'GL-01')->first())->unit_cost->toBe(850)
        ->and($request->refresh()->status)->toBe(PurchaseRequestStatus::Ordered);

    expect(fn () => Purchasing::receive($order, [['line_id' => $line->id, 'qty' => 2.5]], $this->perawat))
        ->toThrow(InvalidArgumentException::class, 'melebihi sisa pesanan');
    expect(StockLedger::available($this->glove->id, $this->branch->id))->toBe(100.0);

    Purchasing::receive($order, [['line_id' => $line->id, 'qty' => 2, 'batch_number' => 'GL-02', 'expired_at' => '2028-09']], $this->perawat);

    expect($order)->status->toBe(PurchaseOrderStatus::Received)
        ->and($order->receipts)->toHaveCount(2)
        ->and(StockLedger::available($this->glove->id, $this->branch->id))->toBe(300.0)
        ->and($request->refresh()->status)->toBe(PurchaseRequestStatus::Received);

    expect(fn () => Purchasing::receive($order, [['line_id' => $line->id, 'qty' => 1]], $this->perawat))
        ->toThrow(InvalidArgumentException::class, 'sudah Diterima lengkap');
});

it('menutup sisa pesanan yang tidak dikirim supplier; pesanan yang belum datang dibatalkan', function () {
    $order = gloveOrder(boxes: 3);
    expect(fn () => Purchasing::closeRemaining($order, 'Kosong di supplier', $this->admin))->toThrow(InvalidArgumentException::class, 'diterima sebagian');

    Purchasing::receive($order, [['line_id' => $order->lines->first()->id, 'qty' => 2]], $this->perawat);
    expect(fn () => Purchasing::cancelOrder($order, 'Salah pesan', $this->admin))->toThrow(InvalidArgumentException::class, 'tidak bisa dibatalkan');

    Purchasing::closeRemaining($order, 'Kosong di supplier', $this->admin);
    expect($order)->status->toBe(PurchaseOrderStatus::Received)->cancel_reason->toBe('Sisa tidak dikirim: Kosong di supplier');
});

it('mengelola kas pembelian: setor, bayar, saldo tidak boleh minus, koreksi lewat entri pembalik', function () {
    expect(fn () => gloveOrder(payNow: true))->toThrow(InvalidArgumentException::class, 'Saldo kas pembelian tidak cukup');
    expect(PurchaseOrder::count())->toBe(0);

    $deposit = Purchasing::deposit($this->branch->id, 500_000, 'Setoran dari owner', $this->owner);
    $order = gloveOrder(payNow: true);

    expect($order)->paid_amount->toBe(170_000)->unpaidAmount()->toBe(0)
        ->and(Purchasing::balance($this->branch->id))->toBe(330_000)
        ->and($order->cashEntries->first())->type->toBe(PurchaseCashType::Payment)->amount->toBe(-170_000)
        ->and($order->cashEntries->first()->description)->toContain('PT Dental Sehat');

    expect(fn () => Purchasing::pay($order, 1, $this->admin))->toThrow(InvalidArgumentException::class, 'sisa yang belum dibayar');
    expect(fn () => Purchasing::expense($this->branch->id, 400_000, 'Ongkir', $this->admin))->toThrow(InvalidArgumentException::class, 'tidak cukup');

    $expense = Purchasing::expense($this->branch->id, 30_000, 'Ongkos kirim', $this->admin);
    expect(Purchasing::balance($this->branch->id))->toBe(300_000);

    $correction = Purchasing::reverseCash($expense, 'Ongkir ditanggung supplier', $this->owner);
    expect($correction)->type->toBe(PurchaseCashType::Correction)->amount->toBe(30_000)->reverses_entry_id->toBe($expense->id)
        ->and(Purchasing::balance($this->branch->id))->toBe(330_000);

    expect(fn () => Purchasing::reverseCash($expense, 'Lagi', $this->owner))->toThrow(InvalidArgumentException::class, 'sudah dikoreksi');
    expect(fn () => Purchasing::reverseCash($order->cashEntries->first(), 'Batal', $this->owner))->toThrow(InvalidArgumentException::class, 'pembatalan pesanan');

    // Setoran tidak bisa dikoreksi bila uangnya sudah terpakai (saldo akan minus).
    expect(fn () => Purchasing::reverseCash($deposit, 'Salah nominal', $this->owner))->toThrow(InvalidArgumentException::class, 'tidak cukup');

    expect(fn () => $deposit->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $deposit->delete())->toThrow(LogicException::class);
});

it('membatalkan pesanan: uang kembali ke kas dan permintaan bisa dipesan ulang', function () {
    Purchasing::deposit($this->branch->id, 200_000, 'Setoran', $this->owner);
    $request = approvedGloveRequest();
    $order = gloveOrder($request->id, payNow: true);
    expect(Purchasing::balance($this->branch->id))->toBe(30_000);

    Purchasing::cancelOrder($order, 'Supplier tutup', $this->admin);

    expect($order)->status->toBe(PurchaseOrderStatus::Cancelled)->paid_amount->toBe(0)->cancel_reason->toBe('Supplier tutup')
        ->and(Purchasing::balance($this->branch->id))->toBe(200_000)
        ->and(PurchaseCashEntry::where('type', PurchaseCashType::Refund)->sum('amount'))->toEqual(170_000)
        ->and($request->refresh())->status->toBe(PurchaseRequestStatus::Approved)->purchase_order_id->toBeNull();

    $reorder = gloveOrder($request->id);
    expect($request->refresh()->purchase_order_id)->toBe($reorder->id)
        ->and(fn () => Purchasing::pay($order, 1_000, $this->admin))->toThrow(InvalidArgumentException::class, 'sudah dibatalkan');
});

it('kas pembelian terpisah per cabang', function () {
    $other = Branch::factory()->create(['code' => 'BKS']);
    Purchasing::deposit($other->id, 100_000, 'Setoran cabang lain', $this->owner);

    expect(Purchasing::balance($this->branch->id))->toBe(0)
        ->and(Purchasing::balance($other->id))->toBe(100_000)
        ->and(fn () => Purchasing::expense($this->branch->id, 10_000, 'Beli kapas', $this->admin))->toThrow(InvalidArgumentException::class, 'tidak cukup');
});

it('membatasi izin pembelian per peran', function () {
    expect($this->perawat->can('Create:PurchaseRequest'))->toBeTrue()
        ->and($this->perawat->can('Receive:Purchase'))->toBeTrue()
        ->and($this->perawat->can('Create:PurchaseOrder'))->toBeFalse()
        ->and($this->perawat->can('Approve:Purchase'))->toBeFalse()
        ->and($this->perawat->can('View:PurchaseCash'))->toBeFalse()
        ->and($this->admin->can('Create:PurchaseOrder'))->toBeTrue()
        ->and($this->admin->can('Manage:PurchaseCash'))->toBeTrue()
        ->and($this->owner->can('Approve:Purchase'))->toBeTrue()
        ->and($this->owner->can('Create:PurchaseOrder'))->toBeFalse();

    expect(StockMovement::count())->toBe(0);
});

it('membatalkan penerimaan dari pesanan: stok dibalik dan pesanan kembali terbuka', function () {
    $request = approvedGloveRequest(2);
    $order = gloveOrder($request->id);
    $line = $order->lines->first();
    $receipt = Purchasing::receive($order, [['line_id' => $line->id, 'qty' => 2, 'batch_number' => 'GL-09']], $this->perawat);
    expect($order->status)->toBe(PurchaseOrderStatus::Received);

    // Lewat buku stok biasa ditolak agar jumlah diterima di pesanan tidak selisih.
    expect(fn () => StockLedger::cancel($receipt, 'Salah input jumlah', $this->admin))->toThrow(InvalidArgumentException::class, 'pesanan pembelian');

    $reversal = Purchasing::cancelReceipt($receipt, 'Salah input jumlah', $this->admin);

    expect($reversal->reverses_movement_id)->toBe($receipt->id)
        ->and(StockLedger::available($this->glove->id, $this->branch->id))->toBe(0.0)
        ->and($order->refresh())->status->toBe(PurchaseOrderStatus::Ordered)
        ->and((float) $line->refresh()->received_quantity)->toBe(0.0)
        ->and($request->refresh()->status)->toBe(PurchaseRequestStatus::Ordered);

    expect(fn () => Purchasing::cancelReceipt($receipt, 'Dibatalkan lagi', $this->admin))->toThrow(InvalidArgumentException::class, 'sudah dibatalkan');
});

/** Jalankan importer untuk satu baris CSV dengan pemetaan kolom tebakan (seperti dialog Import). */
function importRow(string $importer, array $row): void
{
    $import = Import::create([
        'file_name' => 'ekspor.csv', 'file_path' => 'ekspor.csv', 'importer' => $importer, 'total_rows' => 1, 'user_id' => test()->admin->id,
    ]);
    $columnMap = collect($importer::getColumns())->mapWithKeys(function (ImportColumn $column) use ($row): array {
        $header = collect(array_keys($row))->first(fn (string $h): bool => in_array($h, [$column->getName(), ...$column->getGuesses()], true));

        return [$column->getName() => $header];
    })->filter()->all();

    (new $importer($import, $columnMap, []))($row);
}

it('mengimpor barang, kategori, dan supplier dari ekspor aplikasi inventori BHP', function () {
    importRow(ItemImporter::class, [
        'kode' => 'BHP-001', 'nama' => 'MASKER', 'merek' => 'Sensi', 'kategori' => 'Disposable', 'satuan' => 'pcs', 'stok_min' => '5.00', 'aktif' => '1',
    ]);
    importRow(ItemImporter::class, [
        'kode' => 'BHP-002', 'nama' => 'ALGINATE', 'merek' => '', 'kategori' => 'disposable', 'satuan' => 'Pcs', 'stok_min' => '1', 'aktif' => '1',
    ]);
    // Impor ulang memperbarui, tidak menggandakan nama merek.
    importRow(ItemImporter::class, [
        'kode' => 'BHP-001', 'nama' => 'MASKER', 'merek' => 'Sensi', 'kategori' => 'Disposable', 'satuan' => 'pcs', 'stok_min' => '10', 'aktif' => '1',
    ]);

    $masker = Item::where('sku', 'BHP-001')->sole();
    expect($masker)->name->toBe('MASKER (Sensi)')->type->toBe(ItemType::Material)->base_unit_id->toBe($this->pcs->id)
        ->and((float) $masker->min_stock)->toBe(10.0)
        ->and($masker->category->name)->toBe('Disposable')
        ->and(Item::where('sku', 'BHP-002')->sole()->item_category_id)->toBe($masker->item_category_id)
        ->and(ItemCategory::count())->toBe(1);

    importRow(SupplierImporter::class, [
        'nama' => 'PT Dental Sehat', 'kontak' => 'Bu Rina', 'telepon' => '0812-0000-0000', 'email' => 'sales@contoh.test', 'alamat' => 'Jakarta', 'catatan' => null,
    ]);
    importRow(SupplierImporter::class, ['nama' => 'Toko Alkes Maju', 'telepon' => '021-000000']);

    expect(Supplier::count())->toBe(2)
        ->and(Supplier::where('name', 'PT Dental Sehat')->sole())->contact_person->toBe('Bu Rina')->phone->toBe('0812-0000-0000');
});
