<?php

use App\Enums\AttendanceType;
use App\Enums\CommissionBase;
use App\Enums\CommissionRecognition;
use App\Enums\CommissionRole;
use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\FeeGroup;
use App\Enums\ItemType;
use App\Enums\PaymentMethod;
use App\Enums\PayoutStatus;
use App\Enums\PractitionerType;
use App\Enums\RefundSource;
use App\Enums\SalaryComponentKind;
use App\Enums\TreatmentCategory;
use App\Models\Branch;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\LabOrder;
use App\Models\Package;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Supplier;
use App\Models\Treatment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\BillingApprovals;
use App\Services\Billing\CashSessions;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PackageSales;
use App\Services\Billing\PaymentRecorder;
use App\Services\Commission\Attendances;
use App\Services\Commission\CommissionEngine;
use App\Services\Commission\CommissionPayouts;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00'));
    $this->branch = Branch::factory()->create(['code' => 'KMG']);
    $this->doctor = Practitioner::factory()->create(['attendance_fee' => 150_000, 'on_call_fee' => 75_000]);
    $this->nurse = Practitioner::factory()->create(['type' => PractitionerType::DentalTherapist]);
    $this->patient = Patient::factory()->create();
    $this->crown = Treatment::factory()->create(['name' => 'Crown PFM', 'category' => TreatmentCategory::Prosthodontic, 'fee_group' => FeeGroup::LabWork, 'base_price' => 1_000_000]);
    $this->filling = Treatment::factory()->create(['name' => 'Tambal komposit', 'category' => TreatmentCategory::Restorative, 'fee_group' => FeeGroup::General, 'base_price' => 400_000]);
    $this->consult = Treatment::factory()->create(['name' => 'Konsultasi', 'category' => TreatmentCategory::Consultation, 'fee_group' => FeeGroup::Consultation, 'base_price' => 100_000]);
    $this->owner = User::factory()->withMfa()->create();
    $this->owner->assignRole('owner');
    $this->kasir = User::factory()->withMfa()->create();
    $this->kasir->assignRole('kasir');
    $this->kasir->branches()->sync([$this->branch->id]);
});

afterEach(fn () => Carbon::setTestNow());

function commissionRule(array $attributes): CommissionRule
{
    return CommissionRule::create([
        'name' => 'Aturan uji', 'role' => CommissionRole::Operator, 'type' => CommissionType::Percent,
        'base' => CommissionBase::Net, 'recognition' => CommissionRecognition::Performed, ...$attributes,
    ]);
}

/** Kunjungan dengan satu tindakan; opsional diskon baris, bahan (BOM, Rp 25.000/unit), dan biaya lab. */
function treatedInvoice(Treatment $treatment, array $options = []): Invoice
{
    $visit = Visit::factory()->inTreatment()->create([
        'branch_id' => test()->branch->id, 'patient_id' => test()->patient->id, 'practitioner_id' => test()->doctor->id,
    ]);
    $procedure = $visit->procedures()->create([
        'treatment_id' => $treatment->id, 'operator_id' => test()->doctor->id, 'tooth_number' => '36',
        'qty' => $options['qty'] ?? 1, 'assistant_id' => $options['assistant'] ?? null,
    ]);

    if ($material = $options['material_units'] ?? 0) {
        $unit = Unit::firstOrCreate(['name' => 'gram']);
        $item = Item::firstOrCreate(['sku' => 'BHN-X'], ['name' => 'Bahan X', 'type' => ItemType::Material, 'base_unit_id' => $unit->id]);
        StockLedger::receive(test()->branch->id, [['item_id' => $item->id, 'unit_id' => $unit->id, 'qty' => 100, 'unit_price' => 25_000, 'batch_number' => 'B1']]);
        $treatment->materials()->updateOrCreate(['item_id' => $item->id], ['qty' => $material, 'unit_id' => $unit->id]);
        StockLedger::usageForVisit($visit->fresh());
    }

    if ($lab = $options['lab'] ?? 0) {
        LabOrder::create([
            'number' => 'LAB-'.$procedure->id, 'branch_id' => test()->branch->id, 'patient_id' => test()->patient->id,
            'visit_id' => $visit->id, 'visit_procedure_id' => $procedure->id, 'practitioner_id' => test()->doctor->id,
            'supplier_id' => Supplier::create(['name' => 'Lab', 'is_lab' => true])->id, 'work_type' => 'crown', 'specification' => 'PFM', 'cost' => $lab,
        ]);
    }

    $invoice = InvoiceBuilder::forVisit($visit->fresh());

    if ($discount = $options['discount'] ?? 0) {
        $invoice->items()->first()->update(['item_discount' => $discount]);
        InvoiceBuilder::recalculate($invoice->fresh());
    }

    return InvoiceBuilder::issue($invoice->fresh());
}

it('menghitung jasa untuk setiap basis', function (CommissionBase $base, int $expectedBase) {
    commissionRule(['percent' => 40, 'base' => $base]);

    // Rp 1.000.000 − diskon Rp 100.000; bahan 2 × Rp 25.000; lab Rp 300.000.
    $invoice = treatedInvoice($this->crown, ['discount' => 100_000, 'material_units' => 2, 'lab' => 300_000]);
    $commission = Commission::sole();

    expect($commission)
        ->invoice_id->toBe($invoice->id)
        ->gross_amount->toBe(1_000_000)
        ->discount_amount->toBe(100_000)
        ->net_amount->toBe(900_000)
        ->material_cost->toBe(50_000)
        ->lab_cost->toBe(300_000)
        ->base_amount->toBe($expectedBase)
        ->amount->toBe((int) round($expectedBase * 0.4))
        ->status->toBe(CommissionStatus::Earned)
        ->and($commission->practitioner_id)->toBe($this->doctor->id)
        ->and($invoice->items()->first()->commission_rule_snapshot['operator']['base'])->toBe($base->value);
})->with([
    'bruto' => [CommissionBase::Gross, 1_000_000],
    'setelah diskon' => [CommissionBase::Net, 900_000],
    'setelah diskon − bahan' => [CommissionBase::NetMinusMaterial, 850_000],
    'setelah diskon − lab' => [CommissionBase::NetMinusLab, 600_000],
    'setelah diskon − bahan − lab' => [CommissionBase::NetMinusMaterialAndLab, 550_000],
]);

it('memilih aturan paling spesifik: dokter+tindakan → dokter+jenis jasa → dokter → tindakan → jenis jasa → default', function () {
    $other = Practitioner::factory()->create();
    $rules = collect([
        commissionRule(['name' => 'default', 'percent' => 30]),
        commissionRule(['name' => 'jenis umum', 'fee_group' => FeeGroup::General, 'percent' => 38]),
        commissionRule(['name' => 'jenis konsultasi', 'fee_group' => FeeGroup::Consultation, 'percent' => 100]),
        commissionRule(['name' => 'tindakan tambal', 'treatment_id' => $this->filling->id, 'percent' => 35]),
        commissionRule(['name' => 'dokter', 'practitioner_id' => $this->doctor->id, 'percent' => 45]),
        commissionRule(['name' => 'dokter+lab', 'practitioner_id' => $this->doctor->id, 'fee_group' => FeeGroup::LabWork, 'percent' => 42]),
        commissionRule(['name' => 'dokter+tambal', 'practitioner_id' => $this->doctor->id, 'treatment_id' => $this->filling->id, 'percent' => 50]),
        commissionRule(['name' => 'nonaktif', 'practitioner_id' => $this->doctor->id, 'treatment_id' => $this->consult->id, 'percent' => 99, 'is_active' => false]),
    ])->filter->is_active;

    $pick = fn (Treatment $t, Practitioner $p): string => CommissionEngine::resolve($rules, $t, $p->id, CommissionRole::Operator)->name;

    expect($pick($this->filling, $this->doctor))->toBe('dokter+tambal')
        ->and($pick($this->crown, $this->doctor))->toBe('dokter+lab')
        ->and($pick($this->consult, $this->doctor))->toBe('dokter')
        ->and($pick($this->filling, $other))->toBe('tindakan tambal')
        ->and($pick($this->consult, $other))->toBe('jenis konsultasi')
        ->and($pick($this->crown, $other))->toBe('default')
        ->and(CommissionEngine::resolve($rules, $this->crown, $other->id, CommissionRole::Assistant))->toBeNull();
});

it('memberi asisten jasa nominal tetap per tindakan', function () {
    commissionRule(['percent' => 40]);
    commissionRule(['role' => CommissionRole::Assistant, 'type' => CommissionType::Fixed, 'fixed_amount' => 10_000, 'percent' => null]);

    treatedInvoice($this->filling, ['qty' => 2, 'assistant' => $this->nurse->id]);

    expect(Commission::where('role', CommissionRole::Assistant)->sole())
        ->practitioner_id->toBe($this->nurse->id)->amount->toBe(20_000)
        ->and(Commission::where('role', CommissionRole::Operator)->sole()->amount)->toBe(320_000);
});

it('jasa ortho diakui per kunjungan kontrol dari nilai paket per kontrol', function () {
    commissionRule(['fee_group' => FeeGroup::Orthodontic, 'percent' => 40, 'base' => CommissionBase::NetMinusMaterialAndLab]);
    $control = Treatment::factory()->create(['category' => TreatmentCategory::Orthodontic, 'fee_group' => FeeGroup::Orthodontic, 'base_price' => 250_000]);
    $package = Package::create(['code' => 'ORT', 'name' => 'Behel metal', 'price' => 8_000_000]);
    $package->items()->create(['treatment_id' => $control->id, 'quota' => 12]);
    PackageSales::sell($this->patient, $package, ['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id, 'down_payment' => 2_000_000, 'installment_count' => 6]);

    $invoice = treatedInvoice($control);
    $line = $invoice->items()->first();

    expect($line->unit_price)->toBe(0)
        ->and($line->package_value)->toBe(666_667)
        ->and(Commission::sole())->gross_amount->toBe(666_667)->base_amount->toBe(666_667)->amount->toBe(266_667);

    // Cicilan paket tidak menghasilkan jasa (jasa ortho dihitung per kontrol, bukan per pembayaran).
    $package = $this->patient->patientPackages()->first();
    InvoiceBuilder::forInstallment($package->installments()->orderBy('sequence')->first());
    expect(Commission::count())->toBe(1);
});

it('jasa "saat lunas" menunggu sampai tagihan lunas', function () {
    commissionRule(['percent' => 40, 'recognition' => CommissionRecognition::Paid]);
    $invoice = treatedInvoice($this->filling);

    expect(Commission::sole()->status)->toBe(CommissionStatus::Pending);

    $session = CashSessions::open($this->kasir, $this->branch->id, 0);
    PaymentRecorder::receive($this->patient, [$invoice->id], [['method' => PaymentMethod::Cash, 'amount' => 200_000]], $session, $this->kasir);
    expect(Commission::sole()->status)->toBe(CommissionStatus::Pending);

    PaymentRecorder::receive($this->patient, [$invoice->id], [['method' => PaymentMethod::Cash, 'amount' => 200_000]], $session, $this->kasir);
    expect(Commission::sole())->status->toBe(CommissionStatus::Earned)->earned_at->not->toBeNull();
});

it('tidak mengubah jasa yang sudah tercatat saat aturan diubah', function () {
    $rule = commissionRule(['percent' => 40]);
    treatedInvoice($this->filling);
    $rule->update(['percent' => 50]);

    expect(Commission::sole()->amount)->toBe(160_000)
        ->and(fn () => Commission::sole()->update(['amount' => 1]))->toThrow(LogicException::class);
});

it('void tagihan sebelum direkap membatalkan jasa; sesudah rekap dibayar membuat entri pembalik di rekap berikutnya', function () {
    commissionRule(['percent' => 40]);
    $kasirRequests = fn (Invoice $invoice) => BillingApprovals::requestInvoiceVoid($invoice->fresh(), 'Salah pasien', $this->kasir);

    $early = treatedInvoice($this->filling);
    $kasirRequests($early);
    BillingApprovals::decideInvoiceVoid($early->fresh(), true, $this->owner);
    expect(Commission::where('invoice_id', $early->id)->sole())->status->toBe(CommissionStatus::Cancelled)->reason->toContain('Salah pasien');

    $late = treatedInvoice($this->filling);
    $payout = CommissionPayouts::generate(now(), $this->owner)->sole();
    CommissionPayouts::finalize($payout, $this->owner);
    CommissionPayouts::markPaid($payout->fresh(), $this->owner);
    expect($payout->fresh())->total->toBe(160_000)->status->toBe(PayoutStatus::Paid)
        ->and(Commission::where('invoice_id', $late->id)->sole()->status)->toBe(CommissionStatus::Paid);

    Carbon::setTestNow('2026-12-03 09:00');
    $kasirRequests($late);
    BillingApprovals::decideInvoiceVoid($late->fresh(), true, $this->owner);

    $reversal = Commission::whereNotNull('reverses_commission_id')->sole();
    expect($reversal)->amount->toBe(-160_000)->status->toBe(CommissionStatus::Earned);

    $december = CommissionPayouts::generate(now(), $this->owner)->sole();
    expect($december->total)->toBe(-160_000);
});

it('refund sebagian membalik jasa sebanding', function () {
    commissionRule(['percent' => 40]);
    $invoice = treatedInvoice($this->filling);
    $session = CashSessions::open($this->kasir, $this->branch->id, 0);
    PaymentRecorder::receive($this->patient, [$invoice->id], [['method' => PaymentMethod::Cash, 'amount' => 400_000]], $session, $this->kasir);

    $refund = BillingApprovals::requestRefund($this->patient, RefundSource::Invoice, 100_000, PaymentMethod::Cash, 'Satu permukaan tidak jadi', $this->kasir, $this->branch->id, $invoice->fresh());
    BillingApprovals::decideRefund($refund, true, $this->owner);
    BillingApprovals::payOutRefund($refund->fresh(), $session, $this->kasir);

    expect(Commission::whereNotNull('reverses_commission_id')->sole()->amount)->toBe(-40_000)
        ->and((int) Commission::sum('amount'))->toBe(120_000);
});

it('rekap bulanan: jasa + uang hadir + tambahan/potongan, final, dibayar; jasa yang sudah dibayar tidak direkap ulang', function () {
    commissionRule(['percent' => 40]);
    treatedInvoice($this->filling);
    treatedInvoice($this->consult);
    Attendances::record($this->doctor, $this->branch->id, today(), AttendanceType::Present, null, $this->owner);
    Attendances::record($this->doctor, $this->branch->id, today()->subDay(), AttendanceType::OnCall, null, $this->owner);

    $payout = CommissionPayouts::generate(now(), $this->owner)->sole();
    CommissionPayouts::addAdjustment($payout, SalaryComponentKind::Deduction, 'PPh 21', 25_000, $this->owner);

    expect($payout->fresh())
        ->commission_total->toBe(200_000)
        ->allowance_total->toBe(225_000)
        ->adjustment_total->toBe(-25_000)
        ->total->toBe(400_000);

    expect(fn () => CommissionPayouts::markPaid($payout->fresh(), $this->owner))->toThrow(InvalidArgumentException::class);
    CommissionPayouts::finalize($payout->fresh(), $this->owner);
    expect(fn () => CommissionPayouts::addAdjustment($payout->fresh(), SalaryComponentKind::Earning, 'Bonus', 1, $this->owner))->toThrow(InvalidArgumentException::class);
    CommissionPayouts::markPaid($payout->fresh(), $this->owner);

    expect(Commission::where('status', CommissionStatus::Paid)->count())->toBe(2);

    Carbon::setTestNow('2026-12-02 10:00');
    expect(CommissionPayouts::generate(now(), $this->owner))->toHaveCount(0);

    $admin = User::factory()->withMfa()->create();
    $admin->assignRole('admin');
    expect(fn () => CommissionPayouts::generate(now(), $admin))->toThrow(InvalidArgumentException::class);
});

it('menyusulkan jasa untuk tagihan yang terbit sebelum aturan dibuat', function () {
    treatedInvoice($this->filling);
    expect(Commission::count())->toBe(0);

    commissionRule(['percent' => 40]);
    $payout = CommissionPayouts::generate(now(), $this->owner)->sole();

    expect(Commission::sole())->amount->toBe(160_000)->rule_snapshot->toHaveKey('catch_up', true)
        ->and($payout->total)->toBe(160_000);
});

it('mencatat uang hadir dari kunjungan selesai dan menolak catatan ganda', function () {
    Visit::factory()->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id, 'status' => 'done', 'visit_date' => today()]);

    expect(Attendances::fillFromVisits($this->branch->id, today(), $this->owner))->toBe(1)
        ->and(Attendances::fillFromVisits($this->branch->id, today(), $this->owner))->toBe(0)
        ->and($this->doctor->fresh()->attendance_fee)->toBe(150_000);
});
