<?php

use App\Enums\AppointmentStatus;
use App\Enums\DepositTransactionType;
use App\Enums\FeeGroup;
use App\Enums\PaymentMethod;
use App\Enums\TreatmentCategory;
use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\MorningHuddle;
use App\Filament\Pages\Reports;
use App\Filament\Widgets\BranchPerformanceWidget;
use App\Filament\Widgets\CashierTodayWidget;
use App\Filament\Widgets\DoctorPerformanceWidget;
use App\Filament\Widgets\FrontDeskWidget;
use App\Filament\Widgets\MyDayWidget;
use App\Filament\Widgets\MyPerformanceWidget;
use App\Filament\Widgets\OverdueBalancesWidget;
use App\Filament\Widgets\ProductionTrendWidget;
use App\Filament\Widgets\ToContactWidget;
use App\Filament\Widgets\TopDiagnosesWidget;
use App\Filament\Widgets\TopTreatmentsWidget;
use App\Filament\Widgets\UnscheduledPlansWidget;
use App\Mail\DailySummaryMail;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\DailyMetric;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Target;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\Visit;
use App\Reports\ReportRegistry;
use App\Reports\ReportRequest;
use App\Services\Billing\BillingApprovals;
use App\Services\Billing\CashSessions;
use App\Services\Billing\DepositLedger;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PaymentRecorder;
use App\Services\Reports\DailyMetrics;
use App\Services\Reports\Kpis;
use App\Services\Reports\Metrics;
use App\Services\Reports\Targets;
use Database\Seeders\CommissionRuleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00')); // Kamis
    DailyMetrics::flush();
    Targets::flush();
    $this->seed(CommissionRuleSeeder::class);
    $this->branch = Branch::factory()->create(['code' => 'KMG', 'name' => 'Kemang']);
    $this->doctor = Practitioner::factory()->create();
    foreach (range(1, 5) as $day) {
        $this->doctor->schedules()->create(['branch_id' => $this->branch->id, 'day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '17:00', 'is_active' => true]);
    }
    $this->filling = Treatment::factory()->create(['category' => TreatmentCategory::Restorative, 'fee_group' => FeeGroup::General, 'base_price' => 400_000]);
    $this->consult = Treatment::factory()->create(['category' => TreatmentCategory::Consultation, 'fee_group' => FeeGroup::Consultation, 'base_price' => 100_000]);
    $this->kasir = User::factory()->withMfa()->create();
    $this->kasir->assignRole('kasir');
    $this->kasir->branches()->sync([$this->branch->id]);
});

afterEach(function () {
    Carbon::setTestNow();
    DailyMetrics::flush();
    Targets::flush();
});

function doneVisit(Patient $patient, Treatment $treatment, string $date, array $attributes = []): Invoice
{
    $visit = Visit::factory()->create([
        'branch_id' => test()->branch->id, 'patient_id' => $patient->id, 'practitioner_id' => test()->doctor->id,
        'visit_date' => $date, 'status' => VisitStatus::Done, ...$attributes,
    ]);
    $visit->procedures()->create(['treatment_id' => $treatment->id, 'operator_id' => test()->doctor->id]);

    return InvoiceBuilder::issue(InvoiceBuilder::forVisit($visit->fresh()), test()->kasir);
}

/** Satu hari kemarin (Rabu 4 Nov) dengan data yang angkanya diketahui. */
function seedYesterday(): array
{
    $old = Patient::factory()->create();
    Carbon::setTestNow('2026-10-01 10:00');
    doneVisit($old, test()->consult, '2026-10-01');

    Carbon::setTestNow('2026-11-04 16:00');
    $new = Patient::factory()->create();
    $paid = doneVisit($new, test()->filling, '2026-11-04', ['arrived_at' => '2026-11-04 09:00', 'started_at' => '2026-11-04 09:20', 'finished_at' => '2026-11-04 10:00']);
    $unpaid = doneVisit($old, test()->consult, '2026-11-04');

    $session = CashSessions::open(test()->kasir, test()->branch->id, 0);
    PaymentRecorder::receive($new, [$paid->id], [['method' => PaymentMethod::Cash, 'amount' => 400_000]], $session, test()->kasir);
    DepositLedger::append($old, test()->branch->id, DepositTransactionType::TopUp, 50_000, ['method' => PaymentMethod::Transfer->value]);

    Appointment::factory()->create(['branch_id' => test()->branch->id, 'practitioner_id' => test()->doctor->id, 'status' => AppointmentStatus::NoShow,
        'start_at' => '2026-11-04 15:00', 'end_at' => '2026-11-04 15:30']);
    test()->doctor->scheduleExceptions()->create(['date' => '2026-11-04', 'start_time' => '13:00', 'end_time' => '15:00', 'reason' => 'Seminar']);

    Carbon::setTestNow('2026-11-05 10:00');

    return compact('paid', 'unpaid', 'old', 'new');
}

it('menghitung KPI harian sesuai definisi', function () {
    seedYesterday();
    $d = today()->subDay();
    $sum = fn (string $metric): int => Metrics::sum($metric, $d, $d, [$this->branch->id]);

    expect($sum('production'))->toBe(500_000)
        ->and($sum('visits'))->toBe(2)
        ->and($sum('new_patients'))->toBe(1)
        ->and($sum('cash_in'))->toBe(450_000)
        ->and($sum('appointments'))->toBe(1)
        ->and($sum('no_shows'))->toBe(1)
        ->and($sum('no_show_value'))->toBe(200_000) // rata-rata 90 hari: (100.000 + 400.000 + 100.000) ÷ 3
        ->and($sum('chair_minutes_available'))->toBe(360)
        ->and($sum('wait_minutes'))->toBe(20)
        ->and($sum('wait_count'))->toBe(1)
        ->and($sum('commission'))->toBe(260_000) // 40% × 400.000 + 100% × 100.000
        ->and(Metrics::grouped('production', $d, $d, [$this->branch->id], null, 'practitioner')[$this->doctor->id])->toBe(500_000);
});

it('membekukan angka hari yang sudah lewat; hitung ulang hanya dengan --force', function () {
    $data = seedYesterday();
    $d = today()->subDay();
    expect(Metrics::sum('production', $d, $d))->toBe(500_000);

    $owner = User::factory()->withMfa()->create();
    $owner->assignRole('owner');
    BillingApprovals::requestInvoiceVoid($data['unpaid']->fresh(), 'Salah input', $this->kasir);
    BillingApprovals::decideInvoiceVoid($data['unpaid']->fresh(), true, $owner);

    expect(Metrics::sum('production', $d, $d))->toBe(500_000);

    $this->artisan('metrics:snapshot', ['date' => $d->toDateString(), '--force' => true])->assertSuccessful();
    expect(Metrics::sum('production', $d, $d))->toBe(400_000);

    $this->artisan('metrics:snapshot', ['date' => today()->toDateString()])->assertFailed();
    $this->artisan('metrics:snapshot', ['--from' => '2026-11-01'])->assertSuccessful();
    expect(DailyMetric::where('metric', DailyMetrics::MARKER)->count())->toBe(4);
});

it('menghitung target harian dari hari kerja di jadwal praktik', function () {
    Target::create(['period' => '2026-11-01', 'branch_id' => $this->branch->id, 'amount' => 21_000_000]);
    Target::create(['period' => '2026-11-01', 'branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id, 'amount' => 10_500_000]);

    expect(Targets::workingDays(now(), $this->branch->id))->toHaveCount(21)
        ->and(Targets::daily(today(), $this->branch->id))->toBe(1_000_000)
        ->and(Targets::daily(Carbon::parse('2026-11-08'), $this->branch->id))->toBeNull() // Minggu
        ->and(Targets::toDate(now(), today()->subDay(), $this->branch->id))->toBe(3_000_000)
        ->and(Targets::daily(today(), $this->branch->id, $this->doctor->id))->toBe(500_000);
});

it('menghitung penerimaan rencana, rencana belum dijadwalkan, dan umur piutang', function () {
    $patient = Patient::factory()->create();
    $accepted = TreatmentPlan::create(['patient_id' => $patient->id, 'practitioner_id' => $this->doctor->id, 'branch_id' => $this->branch->id,
        'title' => 'Tambal 14, 15', 'status' => TreatmentPlanStatus::Accepted, 'presented_at' => '2026-11-02 10:00', 'decided_at' => '2026-11-02 10:30']);
    $accepted->items()->create(['treatment_id' => $this->filling->id, 'phase' => 1, 'sequence' => 1, 'price_estimate' => 400_000, 'status' => TreatmentPlanItemStatus::Planned]);
    $accepted->items()->create(['treatment_id' => $this->filling->id, 'phase' => 1, 'sequence' => 2, 'price_estimate' => 600_000, 'status' => TreatmentPlanItemStatus::Planned]);
    $rejected = TreatmentPlan::create(['patient_id' => $patient->id, 'practitioner_id' => $this->doctor->id, 'branch_id' => $this->branch->id,
        'title' => 'Crown', 'status' => TreatmentPlanStatus::Rejected, 'presented_at' => '2026-11-03 10:00']);
    $rejected->items()->create(['treatment_id' => $this->consult->id, 'phase' => 1, 'sequence' => 1, 'price_estimate' => 600_000, 'status' => TreatmentPlanItemStatus::Planned]);

    $acceptance = Kpis::caseAcceptance(now()->startOfMonth(), today(), [$this->branch->id]);
    expect($acceptance)->presented->toBe(1_600_000)->accepted->toBe(1_000_000)->ratio->toBe(0.625)
        ->and(Kpis::unscheduledPlans([$this->branch->id])->sum('value'))->toBe(1_000_000);

    Appointment::factory()->create(['branch_id' => $this->branch->id, 'patient_id' => $patient->id, 'practitioner_id' => $this->doctor->id,
        'status' => AppointmentStatus::Booked, 'start_at' => '2026-11-10 09:00', 'end_at' => '2026-11-10 09:30']);
    expect(Kpis::unscheduledPlans([$this->branch->id]))->toBeEmpty();

    Carbon::setTestNow('2026-09-25 10:00');
    doneVisit(Patient::factory()->create(), $this->filling, '2026-09-25');
    Carbon::setTestNow('2026-11-05 10:00');

    expect(Kpis::receivablesAging([$this->branch->id]))->toMatchArray(['0–30' => 0, '31–60' => 400_000, '61–90' => 0, '>90' => 0])
        ->and(Kpis::overdueInvoices([$this->branch->id])->first()['age'])->toBe(41);
});

it('owner bisa membuat semua laporan; dokter hanya miliknya; kasir hanya arus kas', function () {
    seedYesterday();
    $owner = User::factory()->withMfa()->create();
    $owner->assignRole('owner');

    foreach (ReportRegistry::all() as $class) {
        $report = ReportRequest::make($owner, ['report' => $class::key(), 'from' => '2026-11-01', 'to' => '2026-11-05', 'group' => 'practitioner']);
        expect($report)->not->toBeNull();
        $columns = $report->columns();

        foreach ($report->rows() as $row) {
            expect($row)->toHaveCount(count($columns), $class::key());
        }
    }

    $revenue = ReportRequest::make($owner, ['report' => 'omzet', 'from' => '2026-11-01', 'to' => '2026-11-05', 'group' => 'branch']);
    expect($revenue->totals()[4])->toBe(500_000);

    $doctorUser = User::findOrFail($this->doctor->user_id);
    $doctorUser->assignRole('dokter');
    $otherDoctor = Practitioner::factory()->create();
    $ownRevenue = ReportRequest::make($doctorUser, ['report' => 'omzet', 'practitioner' => $otherDoctor->id, 'from' => '2026-11-01', 'to' => '2026-11-05']);
    expect($ownRevenue->filters()->practitionerId)->toBe($this->doctor->id)
        ->and(ReportRequest::make($doctorUser, ['report' => 'laba-kotor']))->toBeNull()
        ->and(array_keys(ReportRegistry::options($doctorUser)))->toBe(['omzet', 'jasa'])
        ->and(array_keys(ReportRegistry::options($this->kasir)))->toBe(['arus-kas']);

    $cash = ReportRequest::make($this->kasir, ['report' => 'arus-kas', 'from' => '2026-11-01', 'to' => '2026-11-05']);
    expect($cash->totals()[count($cash->columns()) - 1])->toBe(450_000);
});

it('mengunduh laporan sebagai Excel dan PDF dengan izin yang sama', function () {
    seedYesterday();
    loginAs('owner');

    $xlsx = $this->get(route('reports.export', ['report' => 'omzet', 'from' => '2026-11-01', 'to' => '2026-11-05', 'format' => 'xlsx']));
    $xlsx->assertOk();
    expect($xlsx->headers->get('content-type'))->toContain('spreadsheetml');

    $pdf = $this->get(route('reports.export', ['report' => 'laba-kotor', 'from' => '2026-11-01', 'to' => '2026-11-05', 'format' => 'pdf']));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');

    $this->actingAs($this->kasir)->get(route('reports.export', ['report' => 'laba-kotor', 'format' => 'xlsx']))->assertForbidden();
    $this->actingAs($this->kasir)->get(route('reports.export', ['report' => 'arus-kas', 'format' => 'xlsx']))->assertOk();
});

it('menampilkan halaman laporan, briefing pagi, dan dashboard per peran', function () {
    seedYesterday();
    Target::create(['period' => '2026-11-01', 'branch_id' => $this->branch->id, 'amount' => 21_000_000]);

    foreach (['owner', 'admin', 'dokter', 'perawat', 'resepsionis', 'kasir'] as $role) {
        $user = $role === 'dokter' ? User::findOrFail($this->doctor->user_id) : User::factory()->withMfa()->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $user->assignRole($role);
        $user->branches()->sync([$this->branch->id]);
        $this->actingAs($user)->get('/')->assertOk();
    }

    loginAs('owner')->branches()->sync([$this->branch->id]);
    Livewire::test(Reports::class)->assertOk()->assertSee('Omzet')
        ->set('filters.report', 'piutang')->assertSee('Piutang')
        ->set('filters.report', 'laba-kotor')->assertSee('Laba kotor');

    $resepsionis = User::factory()->withMfa()->create();
    $resepsionis->assignRole('resepsionis');
    $resepsionis->branches()->sync([$this->branch->id]);
    $this->actingAs($resepsionis);
    Livewire::test(MorningHuddle::class)->assertOk()
        ->assertSee('Kemarin')->assertSee('Rp 500.000')->assertSee('50%') // produksi 500 rb vs target harian 1 jt
        ->assertSee('Slot kosong hari ini');
});

it('mengirim ringkasan harian ke owner tanpa data pasien', function () {
    Mail::fake();
    seedYesterday();
    $owner = User::factory()->withMfa()->create(['email' => 'owner@klinik.test']);
    $owner->assignRole('owner');

    $this->artisan('reports:daily-summary', ['--date' => '2026-11-04'])->assertSuccessful();

    Mail::assertSent(DailySummaryMail::class, function (DailySummaryMail $mail) use ($owner): bool {
        $html = $mail->render();

        return $mail->hasTo($owner->email) && str_contains($html, '500.000') && ! str_contains($html, Patient::first()->name);
    });
});

it('merender setiap widget dashboard Fase 6 dengan data', function () {
    seedYesterday();
    $owner = User::factory()->withMfa()->create();
    $owner->assignRole('owner');
    $owner->branches()->sync([$this->branch->id]);
    $this->actingAs($owner);

    Livewire::test(BranchPerformanceWidget::class)->assertOk()->assertSee('Kemang')->assertSee('500.000')
        ->set('period', 'last_month')->assertOk();
    Livewire::test(ProductionTrendWidget::class)->assertOk();
    Livewire::test(DoctorPerformanceWidget::class)->assertOk()->assertSee('500.000');
    Livewire::test(TopTreatmentsWidget::class)->assertOk()->assertSee($this->filling->name);
    Livewire::test(UnscheduledPlansWidget::class)->assertOk();
    Livewire::test(OverdueBalancesWidget::class)->assertOk()->assertSee('31–60');
    Livewire::test(TopDiagnosesWidget::class)->assertOk();
    expect(FrontDeskWidget::canView())->toBeFalse();

    $admin = User::factory()->withMfa()->create();
    $admin->assignRole('admin');
    $admin->branches()->sync([$this->branch->id]);
    $this->actingAs($admin);
    Livewire::test(FrontDeskWidget::class)->assertOk()->assertSee('Janji hari ini');
    Livewire::test(ToContactWidget::class)->assertOk();
    expect(TopTreatmentsWidget::canView())->toBeFalse(); // laba: owner saja

    $this->actingAs($this->kasir);
    Livewire::test(CashierTodayWidget::class)->assertOk()->assertSee('Diterima hari ini');

    $doctor = User::findOrFail($this->doctor->user_id);
    $doctor->assignRole('dokter');
    $doctor->branches()->sync([$this->branch->id]);
    $this->actingAs($doctor);
    Livewire::test(MyPerformanceWidget::class)->assertOk()->assertSee('Rp 500.000');
    Livewire::test(MyDayWidget::class)->assertOk()->assertSee('Pasien saya hari ini');
});
