<?php

use App\Enums\CashDifferenceReason;
use App\Enums\InstallmentStatus;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\ItemType;
use App\Enums\PatientPackageStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Enums\VisitStatus;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Package;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Treatment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\BillingApprovals;
use App\Services\Billing\CashSessions;
use App\Services\Billing\DepositLedger;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PackageSales;
use App\Services\Billing\PaymentRecorder;
use App\Services\Clinic\MedicalRecordSigning;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:30'));
    $this->branch = Branch::factory()->create(['code' => 'KMG']);
    $this->doctor = Practitioner::factory()->create();
    $this->patient = Patient::factory()->create();
    $this->kasir = User::factory()->withMfa()->create();
    $this->kasir->assignRole('kasir');
    $this->owner = User::factory()->withMfa()->create();
    $this->owner->assignRole('owner');
    $this->actingAs($this->kasir);
    $this->session = CashSessions::open($this->kasir, $this->branch->id, 500_000);
});

afterEach(fn () => Carbon::setTestNow());

/** Kunjungan selesai dengan tindakan (tarif snapshot). */
function billedVisit(array $procedures): Visit
{
    $visit = Visit::factory()->inTreatment()->create([
        'branch_id' => test()->branch->id, 'patient_id' => test()->patient->id, 'practitioner_id' => test()->doctor->id,
    ]);

    foreach ($procedures as [$treatment, $tooth, $surfaces]) {
        $visit->procedures()->create(['treatment_id' => $treatment->id, 'tooth_number' => $tooth, 'surfaces' => $surfaces, 'operator_id' => test()->doctor->id]);
    }

    return $visit;
}

function pay(array $invoiceIds, array $lines, ?int $tendered = null)
{
    return PaymentRecorder::receive(test()->patient, $invoiceIds, $lines, test()->session, test()->kasir, $tendered);
}

it('membuat tagihan dari tindakan & resep, menerbitkan dengan nomor cabang, lalu mengunci baris', function () {
    $filling = Treatment::factory()->create(['name' => 'Tambal komposit', 'base_price' => 400_000]);
    $visit = billedVisit([[$filling, '36', 'O'], [$filling, '37', 'MO']]);

    $tablet = Unit::create(['name' => 'tablet']);
    $strip = Unit::create(['name' => 'strip']);
    $paracetamol = Item::create(['sku' => 'PCT500', 'name' => 'Parasetamol 500 mg', 'type' => ItemType::Medicine, 'base_unit_id' => $tablet->id, 'sell_price' => 1_500]);
    ItemUnit::create(['item_id' => $paracetamol->id, 'unit_id' => $strip->id, 'factor_to_base' => 10]);
    $prescription = $visit->prescription()->create(['practitioner_id' => $this->doctor->id]);
    $prescription->items()->create(['item_id' => $paracetamol->id, 'qty' => 1, 'unit_id' => $strip->id, 'signa' => '3×1']);

    $invoice = InvoiceBuilder::forVisit($visit);

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->items()->pluck('description')->all())->toBe(['Tambal komposit', 'Tambal komposit', 'Parasetamol 500 mg · resep'])
        ->and($invoice->items()->get()->last()->unit_price)->toBe(15_000)
        ->and($invoice->total)->toBe(815_000)
        ->and(InvoiceBuilder::forVisit($visit)->id)->toBe($invoice->id);

    $invoice->update(['invoice_discount' => 15_000]);
    InvoiceBuilder::recalculate($invoice);
    InvoiceBuilder::issue($invoice);

    $invoice->refresh();
    expect($invoice->invoice_number)->toBe('KMG-2611-0001')
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->total)->toBe(800_000)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Billed)
        ->and(fn () => $invoice->items()->first()->update(['unit_price' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $invoice->update(['total' => 1]))->toThrow(LogicException::class)
        ->and(fn () => InvoiceBuilder::recalculate($invoice))->toThrow(InvalidArgumentException::class);
});

it('mengalokasikan pembayaran ke tagihan terlama dulu dan menghitung kembalian tunai', function () {
    $treatment = Treatment::factory()->create(['base_price' => 450_000]);
    $old = InvoiceBuilder::issue(InvoiceBuilder::forVisit(billedVisit([[$treatment, null, null]])));
    Carbon::setTestNow(now()->addHour());
    $today = InvoiceBuilder::forVisit(billedVisit([[Treatment::factory()->create(['base_price' => 915_000]), null, null]]));

    $transaction = pay([$today->id, $old->id], [
        ['method' => 'qris', 'amount' => 1_000_000, 'reference_no' => '2611-8812'],
        ['method' => 'cash', 'amount' => 365_000],
    ], tendered: 400_000);

    expect($transaction->total)->toBe(1_365_000)
        ->and($transaction->change_given)->toBe(35_000)
        ->and($old->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($today->fresh())->status->toBe(InvoiceStatus::Paid)->invoice_number->toBe('KMG-2611-0002')
        ->and($transaction->payments()->orderBy('id')->get()->map(fn ($p) => [$p->invoice_id, $p->method->value, $p->amount])->all())
        ->toBe([[$old->id, 'qris', 450_000], [$today->id, 'qris', 550_000], [$today->id, 'cash', 365_000]])
        ->and(CashSessions::systemTotals($this->session))->toMatchArray(['cash' => 365_000, 'qris' => 1_000_000])
        ->and(CashSessions::expectedCash($this->session))->toBe(865_000);
});

it('menolak bayar lebih (non-tunai) dan menandai dibayar sebagian', function () {
    $invoice = InvoiceBuilder::issue(InvoiceBuilder::forVisit(billedVisit([[Treatment::factory()->create(['base_price' => 300_000]), null, null]])));

    expect(fn () => pay([$invoice->id], [['method' => 'transfer', 'amount' => 300_001]]))->toThrow(InvalidArgumentException::class);

    pay([$invoice->id], [['method' => 'debit', 'amount' => 100_000]]);

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Partial)->paid_total->toBe(100_000)
        ->and($this->patient->outstandingBalance())->toBe(200_000);
});

it('memakai deposit sebagai metode bayar; void pembayaran deposit mengembalikan saldo (4 mata)', function () {
    DepositLedger::topUp($this->patient, $this->session, 250_000, PaymentMethod::Transfer);
    $invoice = InvoiceBuilder::issue(InvoiceBuilder::forVisit(billedVisit([[Treatment::factory()->create(['base_price' => 200_000]), null, null]])));

    pay([$invoice->id], [['method' => 'deposit', 'amount' => 200_000]]);
    $payment = $invoice->payments()->first();

    expect($this->patient->depositBalance())->toBe(50_000)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(CashSessions::systemTotals($this->session)['transfer'])->toBe(250_000)
        ->and(fn () => pay([$invoice->id], [['method' => 'deposit', 'amount' => 1]]))->toThrow(InvalidArgumentException::class);

    BillingApprovals::requestPaymentVoid($payment, 'Salah pilih metode', $this->kasir);

    expect(fn () => BillingApprovals::decidePaymentVoid($payment->fresh(), true, $this->kasir))->toThrow(InvalidArgumentException::class);

    BillingApprovals::decidePaymentVoid($payment->fresh(), true, $this->owner);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Void)
        ->and($invoice->fresh())->status->toBe(InvoiceStatus::Issued)->paid_total->toBe(0)
        ->and($this->patient->depositBalance())->toBe(250_000);
});

it('void tagihan hanya tanpa pembayaran, lalu mengembalikan kunjungan & kuota paket', function () {
    $control = Treatment::factory()->create(['name' => 'Kontrol behel', 'base_price' => 250_000]);
    $package = Package::create(['code' => 'ORT', 'name' => 'Behel metal', 'price' => 8_000_000]);
    $package->items()->create(['treatment_id' => $control->id, 'quota' => 2]);
    $patientPackage = PackageSales::sell($this->patient, $package, ['branch_id' => $this->branch->id, 'down_payment' => 8_000_000]);

    $invoice = InvoiceBuilder::forVisit(billedVisit([[$control, null, null]]));
    expect($invoice->items()->first())->unit_price->toBe(0)->patient_package_id->toBe($patientPackage->id)
        ->and($invoice->total)->toBe(0);

    InvoiceBuilder::issue($invoice);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($patientPackage->remainingQuota())->toBe([$control->id => 1]);

    // Kunjungan kedua & ketiga: kuota tinggal 1, lalu tarif normal.
    $second = InvoiceBuilder::issue(InvoiceBuilder::forVisit(billedVisit([[$control, null, null]])));
    $third = InvoiceBuilder::forVisit(billedVisit([[$control, null, null]]));
    expect($second->total)->toBe(0)->and($third->total)->toBe(250_000);

    InvoiceBuilder::issue($third);
    pay([$third->id], [['method' => 'cash', 'amount' => 250_000]]);
    expect(fn () => BillingApprovals::requestInvoiceVoid($third->fresh(), 'Salah pasien', $this->kasir))->toThrow(InvalidArgumentException::class);

    BillingApprovals::requestInvoiceVoid($second->fresh(), 'Kontrol tidak jadi dilakukan', $this->kasir);
    BillingApprovals::decideInvoiceVoid($second->fresh(), true, $this->owner);

    expect($second->fresh()->status)->toBe(InvoiceStatus::Void)
        ->and($second->visit->fresh()->status)->toBe(VisitStatus::InTreatment)
        ->and($patientPackage->remainingQuota())->toBe([$control->id => 1]);
});

it('refund: diajukan, disetujui owner, diserahkan kasir; sesi kasir & status tagihan ikut berubah', function () {
    $invoice = InvoiceBuilder::issue(InvoiceBuilder::forVisit(billedVisit([[Treatment::factory()->create(['base_price' => 500_000]), null, null]])));
    pay([$invoice->id], [['method' => 'cash', 'amount' => 500_000]]);
    $invoice->refresh();

    expect(fn () => BillingApprovals::requestRefund($this->patient, RefundSource::Invoice, 600_000, PaymentMethod::Cash, 'Batal tindakan', $this->kasir, $this->branch->id, $invoice))
        ->toThrow(InvalidArgumentException::class);

    $refund = BillingApprovals::requestRefund($this->patient, RefundSource::Invoice, 200_000, PaymentMethod::Cash, 'Satu gigi batal ditambal', $this->kasir, $this->branch->id, $invoice);

    expect(fn () => BillingApprovals::payOutRefund($refund, $this->session, $this->kasir))->toThrow(InvalidArgumentException::class);

    BillingApprovals::decideRefund($refund, true, $this->owner);
    BillingApprovals::payOutRefund($refund->fresh(), $this->session, $this->kasir);

    expect($refund->fresh()->status)->toBe(RefundStatus::Paid)
        ->and($invoice->fresh())->status->toBe(InvoiceStatus::Partial)->paid_total->toBe(300_000)
        ->and(CashSessions::expectedCash($this->session))->toBe(800_000);
});

it('tutup kasir: wajib cocokkan metode non-tunai dan beri alasan bila ada selisih', function () {
    $invoice = InvoiceBuilder::issue(InvoiceBuilder::forVisit(billedVisit([[Treatment::factory()->create(['base_price' => 300_000]), null, null]])));
    pay([$invoice->id], [['method' => 'cash', 'amount' => 100_000], ['method' => 'qris', 'amount' => 200_000]]);

    $counted = ['100000' => 5, '50000' => 1, '20000' => 2, 'coins' => 0]; // 590.000; sistem 600.000

    expect(fn () => CashSessions::close($this->session, $this->kasir, ['denominations' => $counted, 'difference_reason' => CashDifferenceReason::Unknown]))
        ->toThrow(InvalidArgumentException::class, 'QRIS')
        ->and(fn () => CashSessions::close($this->session, $this->kasir, ['denominations' => $counted, 'reported' => ['qris' => 200_000]]))
        ->toThrow(InvalidArgumentException::class, 'selisih');

    $closed = CashSessions::close($this->session, $this->kasir, [
        'denominations' => $counted, 'reported' => ['qris' => 200_000],
        'difference_reason' => 'short_change', 'next_opening_cash' => 500_000,
    ]);

    expect($closed)
        ->expected_cash->toBe(600_000)->counted_cash->toBe(590_000)->difference->toBe(-10_000)
        ->deposited_to_safe->toBe(90_000)
        ->and($closed->method_totals['qris'])->toBe(['system' => 200_000, 'reported' => 200_000, 'matched' => true])
        ->and(CashSessions::suggestedOpeningCash($this->branch->id))->toBe(500_000)
        ->and(fn () => pay([$invoice->id], [['method' => 'cash', 'amount' => 1]]))->toThrow(InvalidArgumentException::class);
});

it('membuat jadwal cicilan paket dan menagih cicilan lewat tagihan biasa', function () {
    $control = Treatment::factory()->create();
    $package = Package::create(['code' => 'ORT', 'name' => 'Behel metal RA + RB', 'price' => 8_000_000, 'validity_days' => 730]);
    $package->items()->create(['treatment_id' => $control->id, 'quota' => 12]);

    $sold = PackageSales::sell($this->patient, $package, [
        'branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id,
        'down_payment' => 2_000_000, 'installment_count' => 6, 'start_date' => '2026-04-10', 'due_day' => 5,
    ]);
    $installments = $sold->installments()->get();

    expect($sold->package_number)->toBe('PKT-2604-0001')
        ->and($installments->map(fn ($i) => [$i->sequence, $i->due_date->toDateString(), $i->amount])->all())->toBe([
            [0, '2026-04-10', 2_000_000], [1, '2026-05-05', 1_000_000], [2, '2026-06-05', 1_000_000], [3, '2026-07-05', 1_000_000],
            [4, '2026-08-05', 1_000_000], [5, '2026-09-05', 1_000_000], [6, '2026-10-05', 1_000_000],
        ])
        ->and(PackageSales::schedule(5_000_000, 0, 3, Carbon::parse('2026-01-31'), 28)[2]['amount'])->toBe(1_668_000);

    $dp = InvoiceBuilder::forInstallment($installments->first());
    expect($dp)->status->toBe(InvoiceStatus::Issued)->total->toBe(2_000_000)
        ->and($dp->items()->first()->item_type)->toBe(InvoiceItemType::Installment)
        ->and($installments->first()->fresh()->status)->toBe(InstallmentStatus::Billed);

    pay([$dp->id], [['method' => 'transfer', 'amount' => 2_000_000]]);

    expect($installments->first()->fresh()->status)->toBe(InstallmentStatus::Paid)
        ->and($sold->fresh()->load('installments')->paidAmount())->toBe(2_000_000)
        ->and($sold->fresh()->status)->toBe(PatientPackageStatus::Active);
});

it('dokter tetap bisa menandatangani kunjungan yang sudah ditagih', function () {
    $this->seed(ReferenceDataSeeder::class);
    $visit = billedVisit([[Treatment::factory()->create(['icd9cm_code' => null]), null, null]]);
    InvoiceBuilder::issue(InvoiceBuilder::forVisit($visit));

    $record = $visit->ensureMedicalRecord();
    $record->update(['subjective' => 'Kontrol']);
    $record->diagnoses()->create(['icd10_code' => 'K02.1']);
    $doctor = loginAs('dokter');

    MedicalRecordSigning::sign($visit->fresh(), $doctor);

    expect($visit->fresh())->status->toBe(VisitStatus::Billed)->isLocked()->toBeTrue();
});
