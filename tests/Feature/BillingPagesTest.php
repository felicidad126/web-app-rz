<?php

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Pages\BillingApprovalsPage;
use App\Filament\Pages\Cashier;
use App\Filament\Resources\CashSessions\Pages\CloseCashSession;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\PatientPackages\Pages\CreatePatientPackage;
use App\Filament\Resources\PatientPackages\Pages\ViewPatientPackage;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\RelationManagers\DepositRelationManager;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\Practitioner;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\BillingApprovals;
use App\Services\Billing\CashSessions;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PaymentRecorder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00'));
    $this->branch = Branch::factory()->create(['code' => 'KMG']);
    $this->doctor = Practitioner::factory()->create();
    $this->patient = Patient::factory()->create(['name' => 'Siti Aminah']);
    $this->treatment = Treatment::factory()->create(['name' => 'Tambal komposit', 'base_price' => 400_000]);
});

afterEach(fn () => Carbon::setTestNow());

/** Pengguna peran tertentu yang ditugaskan di cabang uji. */
function billingUser(string $role): User
{
    $user = User::factory()->withMfa()->create();
    $user->assignRole($role);
    $user->branches()->sync([test()->branch->id]);
    test()->actingAs($user);

    return $user;
}

function treatedVisit(): Visit
{
    $visit = Visit::factory()->inTreatment()->create([
        'branch_id' => test()->branch->id, 'patient_id' => test()->patient->id, 'practitioner_id' => test()->doctor->id,
    ]);
    $visit->procedures()->create(['treatment_id' => test()->treatment->id, 'tooth_number' => '36', 'surfaces' => 'O', 'operator_id' => test()->doctor->id]);

    return $visit;
}

it('kasir membuat tagihan dari halaman kasir, memberi diskon, lalu menerima pembayaran (sesi dibuka otomatis)', function () {
    $visit = treatedVisit();
    $kasir = billingUser('kasir');

    Livewire::test(Cashier::class)
        ->assertSee('Siti Aminah')
        ->callTableAction('checkout', $visit)
        ->assertRedirect();

    $invoice = Invoice::where('visit_id', $visit->id)->firstOrFail();
    expect($invoice->status)->toBe(InvoiceStatus::Draft)->and($invoice->total)->toBe(400_000);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->fillForm(['invoice_discount' => 50_000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($invoice->fresh()->total)->toBe(350_000);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction('receivePayment', data: [
            'opening_cash' => 300_000,
            'payments' => [['method' => 'cash', 'amount' => 350_000]],
            'cash_tendered' => 400_000,
        ])
        ->assertHasNoActionErrors();

    $invoice->refresh();
    $session = CashSession::firstOrFail();

    expect($invoice)->status->toBe(InvoiceStatus::Paid)->invoice_number->toBe('KMG-2611-0001')
        ->and($session)->user_id->toBe($kasir->id)->opening_cash->toBe(300_000)
        ->and($invoice->payments()->first()->transaction->change_given)->toBe(50_000)
        ->and($visit->fresh()->status->value)->toBe('billed');
});

it('menutup sesi kasir dari halaman tutup kasir dengan hitung pecahan', function () {
    $kasir = billingUser('kasir');
    $session = CashSessions::open($kasir, $this->branch->id, 200_000);
    $invoice = InvoiceBuilder::issue(InvoiceBuilder::forVisit(treatedVisit()));
    PaymentRecorder::receive($this->patient, [$invoice->id], [['method' => 'cash', 'amount' => 400_000]], $session, $kasir);

    Livewire::test(CloseCashSession::class, ['record' => $session->getRouteKey()])
        ->assertSee('Tutup kasir')
        ->fillForm(['denominations' => ['100000' => 6], 'next_opening_cash' => 200_000])
        ->call('close')
        ->assertRedirect();

    expect($session->fresh())->closed_at->not->toBeNull()->counted_cash->toBe(600_000)->difference->toBe(0)->deposited_to_safe->toBe(400_000);
});

it('owner menyetujui void pembayaran dari halaman persetujuan; kasir tidak bisa membuka halaman itu', function () {
    $kasir = billingUser('kasir');
    $session = CashSessions::open($kasir, $this->branch->id, 0);
    $invoice = InvoiceBuilder::issue(InvoiceBuilder::forVisit(treatedVisit()));
    PaymentRecorder::receive($this->patient, [$invoice->id], [['method' => 'debit', 'amount' => 400_000]], $session, $kasir);
    $payment = $invoice->payments()->first();
    BillingApprovals::requestPaymentVoid($payment, 'Salah pilih metode', $kasir);

    $this->get('/persetujuan')->assertForbidden();

    billingUser('owner');

    Livewire::test(BillingApprovalsPage::class)
        ->assertSee('Salah pilih metode')
        ->callAction('decide', arguments: ['type' => 'payment', 'id' => $payment->id, 'approve' => true])
        ->assertNotified('Disetujui');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Void)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Issued);
});

it('menjual paket dari form lalu menerima DP dari halaman paket', function () {
    $package = Package::create(['code' => 'ORT', 'name' => 'Behel metal', 'price' => 8_000_000]);
    $package->items()->create(['treatment_id' => $this->treatment->id, 'quota' => 12]);
    billingUser('kasir');

    Livewire::test(CreatePatientPackage::class)
        ->fillForm([
            'patient_id' => $this->patient->id, 'package_id' => $package->id, 'branch_id' => $this->branch->id,
            'start_date' => '2026-11-05', 'price' => 8_000_000, 'down_payment' => 2_000_000, 'installment_count' => 6, 'due_day' => 5,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sold = PatientPackage::firstOrFail();
    expect($sold->installments()->count())->toBe(7);

    Livewire::test(ViewPatientPackage::class, ['record' => $sold->getRouteKey()])
        ->callAction('payInstallment', data: ['opening_cash' => 0, 'method' => PaymentMethod::Transfer->value, 'reference_no' => 'TRF-1'])
        ->assertHasNoActionErrors();

    expect($sold->installments()->where('sequence', 0)->first()->status)->toBe(InstallmentStatus::Paid);
});

it('top up deposit dari tab Deposit pasien', function () {
    billingUser('kasir');

    Livewire::test(DepositRelationManager::class, ['ownerRecord' => $this->patient, 'pageClass' => ViewPatient::class])
        ->callTableAction('topUp', data: ['opening_cash' => 0, 'amount' => 250_000, 'method' => 'qris'])
        ->assertHasNoTableActionErrors();

    expect($this->patient->depositBalance())->toBe(250_000);
});

it('struk dan nota hanya untuk peran yang boleh melihat tagihan', function () {
    $kasir = billingUser('kasir');
    $session = CashSessions::open($kasir, $this->branch->id, 0);
    $invoice = InvoiceBuilder::issue(InvoiceBuilder::forVisit(treatedVisit()));
    $transaction = PaymentRecorder::receive($this->patient, [$invoice->id], [['method' => 'cash', 'amount' => 400_000]], $session, $kasir, 500_000);

    $this->get(route('billing.receipt', $transaction))->assertOk()->assertSee('KMG-2611-0001')->assertSee('Kembali')->assertSee('100.000');
    $this->get(route('billing.invoice-receipt', $invoice).'?w=58')->assertOk()->assertSee('size: 58mm auto', false);
    $this->get(route('billing.invoice-pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');

    billingUser('dokter');
    $this->get(route('billing.receipt', $transaction))->assertForbidden();

    // Kasir cabang lain tidak boleh melihat struk cabang ini.
    $other = User::factory()->withMfa()->create();
    $other->assignRole('kasir');
    $other->branches()->sync([Branch::factory()->create()->id]);
    $this->actingAs($other)->get(route('billing.invoice-pdf', $invoice))->assertForbidden();
});
