<?php

use App\Enums\AttendanceType;
use App\Enums\CommissionBase;
use App\Enums\CommissionRole;
use App\Enums\CommissionType;
use App\Enums\FeeGroup;
use App\Enums\PayoutStatus;
use App\Enums\SalaryComponentKind;
use App\Enums\TreatmentCategory;
use App\Enums\VisitStatus;
use App\Filament\Resources\CommissionPayouts\Pages\ListCommissionPayouts;
use App\Filament\Resources\CommissionPayouts\Pages\ViewCommissionPayout;
use App\Filament\Resources\CommissionRules\Pages\ManageCommissionRules;
use App\Filament\Resources\Payslips\Pages\EditPayslip;
use App\Filament\Resources\Payslips\Pages\ListPayslips;
use App\Filament\Resources\PractitionerAttendances\Pages\ManagePractitionerAttendances;
use App\Models\Branch;
use App\Models\CommissionPayout;
use App\Models\CommissionRule;
use App\Models\Patient;
use App\Models\Payslip;
use App\Models\Practitioner;
use App\Models\PractitionerAttendance;
use App\Models\SalaryComponent;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\InvoiceBuilder;
use Database\Seeders\CommissionRuleSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00'));
    $this->seed(CommissionRuleSeeder::class);
    $this->branch = Branch::factory()->create(['code' => 'KMG']);
    $this->doctor = Practitioner::factory()->create(['attendance_fee' => 150_000]);
    $this->doctorUser = User::findOrFail($this->doctor->user_id);
    $this->doctorUser->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $this->doctorUser->assignRole('dokter');
    $this->doctorUser->branches()->sync([$this->branch->id]);
    $this->treatment = Treatment::factory()->create(['name' => 'Tambal komposit', 'category' => TreatmentCategory::Restorative, 'fee_group' => FeeGroup::General, 'base_price' => 400_000]);
});

afterEach(fn () => Carbon::setTestNow());

function payrollVisit(Practitioner $doctor): void
{
    $visit = Visit::factory()->create(['branch_id' => test()->branch->id, 'patient_id' => Patient::factory()->create()->id,
        'practitioner_id' => $doctor->id, 'status' => VisitStatus::Done, 'visit_date' => today()]);
    $visit->procedures()->create(['treatment_id' => test()->treatment->id, 'operator_id' => $doctor->id]);
    InvoiceBuilder::issue(InvoiceBuilder::forVisit($visit->fresh()));
}

function ownerUser(): User
{
    $owner = loginAs('owner');
    $owner->branches()->sync([test()->branch->id]);

    return $owner;
}

it('owner membuat aturan jasa dari halaman Aturan jasa', function () {
    ownerUser();

    Livewire::test(ManageCommissionRules::class)
        ->assertOk()->assertSee('Konsultasi: 100% setelah diskon')
        ->callAction('create', [
            'name' => 'Asisten Rp 10.000 per tindakan', 'role' => CommissionRole::Assistant->value, 'type' => CommissionType::Fixed->value,
            'fixed_amount' => 10_000, 'recognition' => 'performed', 'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(CommissionRule::where('role', CommissionRole::Assistant)->sole())->fixed_amount->toBe(10_000)->type->toBe(CommissionType::Fixed);

    Livewire::test(ManageCommissionRules::class)
        ->callAction('create', ['name' => 'Tanpa persen', 'role' => 'operator', 'type' => 'percent', 'base' => CommissionBase::Net->value, 'recognition' => 'performed'])
        ->assertHasActionErrors(['percent' => 'required']);
});

it('owner membuat rekap, menambah potongan, memfinalkan, membayar, dan mencetak slip', function () {
    payrollVisit($this->doctor);
    PractitionerAttendance::create(['practitioner_id' => $this->doctor->id, 'branch_id' => $this->branch->id, 'date' => today(), 'type' => AttendanceType::Present, 'amount' => 150_000]);
    ownerUser();

    Livewire::test(ListCommissionPayouts::class)
        ->callAction('generate', ['period' => '2026-11-01'])
        ->assertNotified('1 rekap draf diperbarui');

    $payout = CommissionPayout::sole();
    expect($payout->total)->toBe(310_000);

    Livewire::test(ListCommissionPayouts::class)->assertOk()->assertSee('160.000')->assertSee('40,0%');

    Livewire::test(ViewCommissionPayout::class, ['record' => $payout->getRouteKey()])
        ->assertOk()->assertSee('Tambal komposit')->assertSee('40% · Setelah diskon')
        ->callAction('addAdjustment', ['kind' => SalaryComponentKind::Deduction->value, 'description' => 'PPh 21', 'amount' => 15_500])
        ->callAction('finalize')
        ->callAction('markPaid');

    expect($payout->fresh())->status->toBe(PayoutStatus::Paid)->total->toBe(294_500);

    $this->get(route('payroll.commission-slip', $payout))->assertOk();
    $excel = $this->get(route('payroll.commission-excel', ['period' => '2026-11']));
    $excel->assertOk();
    expect($excel->headers->get('content-type'))->toContain('spreadsheetml');
});

it('dokter hanya melihat rekap & slip jasanya sendiri dan tidak bisa membuat rekap', function () {
    $other = Practitioner::factory()->create();
    payrollVisit($this->doctor);
    payrollVisit($other);
    ownerUser();
    Livewire::test(ListCommissionPayouts::class)->callAction('generate', ['period' => '2026-11-01']);
    $mine = CommissionPayout::where('practitioner_id', $this->doctor->id)->sole();
    $theirs = CommissionPayout::where('practitioner_id', $other->id)->sole();

    $this->actingAs($this->doctorUser);
    Livewire::test(ListCommissionPayouts::class)
        ->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs])
        ->assertActionHidden('generate');

    $this->get(route('payroll.commission-slip', $mine))->assertOk();
    $this->get(route('payroll.commission-slip', $theirs))->assertForbidden();
    $this->get('/jasa-dokter/'.$theirs->id)->assertNotFound();
});

it('admin mencatat kehadiran dokter dari kunjungan dan secara manual', function () {
    Visit::factory()->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id, 'status' => VisitStatus::Done, 'visit_date' => today()]);
    loginAs('admin')->branches()->sync([$this->branch->id]);

    Livewire::test(ManagePractitionerAttendances::class)
        ->callAction('fromVisits', ['branch_id' => $this->branch->id, 'date' => today()->toDateString()])
        ->assertNotified('1 kehadiran dicatat')
        ->callAction('create', ['practitioner_id' => $this->doctor->id, 'branch_id' => $this->branch->id, 'date' => today()->subDay()->toDateString(),
            'type' => AttendanceType::OnCall->value, 'amount' => 75_000])
        ->assertHasNoActionErrors();

    expect(PractitionerAttendance::count())->toBe(2)
        ->and(PractitionerAttendance::where('type', AttendanceType::Present)->sole()->amount)->toBe(150_000);
});

it('owner membuat slip gaji dari komponen karyawan, mengubah, memfinalkan, dan mengunduh', function () {
    $owner = ownerUser();
    $staff = User::factory()->create(['name' => 'Rina Resepsionis']);
    $basic = SalaryComponent::where('name', 'Gaji pokok')->sole();
    $meal = SalaryComponent::where('name', 'Uang makan')->sole();
    $staff->salaryItems()->create(['salary_component_id' => $basic->id, 'amount' => 4_000_000]);
    $staff->salaryItems()->create(['salary_component_id' => $meal->id, 'amount' => 500_000]);

    Livewire::test(ListPayslips::class)->callAction('generate', ['period' => '2026-11-01'])->assertNotified('1 slip draf dibuat');
    $payslip = Payslip::sole();
    expect($payslip->net_total)->toBe(4_500_000);

    $lines = Livewire::test(EditPayslip::class, ['record' => $payslip->getRouteKey()])->get('data.lines');
    $lines['new'] = ['kind' => SalaryComponentKind::Deduction->value, 'name' => 'PPh 21', 'amount' => 75_000];

    Livewire::test(EditPayslip::class, ['record' => $payslip->getRouteKey()])
        ->set('data.lines', $lines)
        ->call('save')->assertHasNoFormErrors()
        ->callAction('finalize');

    expect($payslip->fresh())->status->toBe(PayoutStatus::Final)->net_total->toBe(4_425_000)->deduction_total->toBe(75_000);

    $this->get(route('payroll.payslip-pdf', $payslip))->assertOk();
    $this->get(route('payroll.payslip-excel', ['period' => '2026-11']))->assertOk();

    $this->actingAs($this->doctorUser)->get(route('payroll.payslip-pdf', $payslip))->assertForbidden();
});
