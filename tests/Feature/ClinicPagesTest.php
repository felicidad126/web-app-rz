<?php

use App\Enums\AppointmentStatus;
use App\Enums\TreatmentPlanStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\QueueBoard;
use App\Filament\Pages\Schedule;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\TreatmentPlans\Pages\EditTreatmentPlan;
use App\Filament\Resources\Visits\Pages\ExamineVisit;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Chair;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\PractitionerSchedule;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:10'));
    $this->seed(ReferenceDataSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->chair = Chair::create(['branch_id' => $this->branch->id, 'name' => 'Unit 1']);
    $this->doctor = Practitioner::factory()->create();
    $this->doctor->user->assignRole('dokter');
    PractitionerSchedule::create([
        'practitioner_id' => $this->doctor->id, 'branch_id' => $this->branch->id, 'chair_id' => $this->chair->id,
        'day_of_week' => 4, 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

/** Pengguna dengan peran & akses ke cabang uji. */
function staff(string $role, ?User $user = null): User
{
    $user ??= User::factory()->withMfa()->create();
    $user->assignRole($role);
    $user->branches()->syncWithoutDetaching([test()->branch->id]);
    test()->actingAs($user);

    return $user;
}

dataset('akses fase 2', [
    ['resepsionis', '/jadwal', 200],
    ['kasir', '/jadwal', 200],
    ['admin', '/janji-temu/create', 200],
    ['kasir', '/janji-temu/create', 403],
    ['dokter', '/kunjungan', 200],
    ['perawat', '/papan-antrean', 200],
    ['dokter', '/rencana-perawatan', 200],
    ['resepsionis', '/rencana-perawatan', 403],
    ['resepsionis', '/recall', 200],
    ['kasir', '/recall', 403],
    ['owner', '/template-persetujuan', 200],
    ['resepsionis', '/template-persetujuan', 403],
]);

it('membatasi halaman fase 2 sesuai matriks akses', function (string $role, string $url, int $status) {
    staff($role);

    $this->get($url)->assertStatus($status);
})->with('akses fase 2');

it('hanya peran klinis & owner yang membuka rekam medis', function () {
    $visit = Visit::factory()->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id]);
    $url = '/kunjungan/'.$visit->id.'/pemeriksaan';

    foreach (['dokter' => 200, 'perawat' => 200, 'owner' => 200, 'admin' => 403, 'resepsionis' => 403, 'kasir' => 403] as $role => $status) {
        staff($role);
        $this->get($url)->assertStatus($status);
    }

    foreach (['dokter' => 200, 'kasir' => 403] as $role => $status) {
        staff($role);
        $this->get('/berkas/odontogram/'.$visit->patient_id)->assertStatus($status);
    }
});

it('menolak janji temu yang bentrok saat dibuat dari form', function () {
    $patient = Patient::factory()->create();
    Appointment::factory()->at('10:00', 45)->create([
        'branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id, 'chair_id' => $this->chair->id,
    ]);
    staff('resepsionis');

    $form = [
        'patient_id' => $patient->id, 'branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id,
        'chair_id' => $this->chair->id, 'date' => '2026-11-05', 'duration' => 30,
    ];

    Livewire::test(CreateAppointment::class)
        ->fillForm([...$form, 'start_time' => '10:30'])
        ->call('create')
        ->assertHasFormErrors(['start_time']);

    Livewire::test(CreateAppointment::class)
        ->fillForm([...$form, 'start_time' => '10:45'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Appointment::where('patient_id', $patient->id)->first())
        ->status->toBe(AppointmentStatus::Booked)
        ->end_at->format('H:i')->toBe('11:15');
});

it('menampilkan papan jadwal dan check-in pasien dari panel detail', function () {
    $appointment = Appointment::factory()->at('09:00')->create([
        'branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id, 'chair_id' => $this->chair->id, 'reason' => 'Tambal 36',
    ]);
    staff('resepsionis');

    Livewire::test(Schedule::class)
        ->assertSee($appointment->patient->name)
        ->call('select', $appointment->id)
        ->assertSee('Tambal 36')
        ->callAction('checkInAppointment', arguments: ['appointment' => $appointment->id])
        ->assertNotified();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Arrived)
        ->and($appointment->visit)->not->toBeNull();
});

it('dokter mengisi pemeriksaan lalu menandatangani; setelah itu terkunci', function () {
    $visit = Visit::factory()->inTreatment()->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id]);
    $treatment = Treatment::factory()->create(['base_price' => 400_000, 'icd9cm_code' => null]);
    staff('dokter', $this->doctor->user);

    $page = Livewire::test(ExamineVisit::class, ['record' => $visit->getRouteKey()]);

    $page->fillForm([
        'medicalRecord.subjective' => 'Ngilu gigi kiri bawah',
        'medicalRecord.vitals.blood_pressure' => '120/80',
        'medicalRecord.diagnoses' => [['icd10_code' => 'K02.1', 'type' => 'primary', 'tooth_number' => '36']],
        'procedures' => [['treatment_id' => $treatment->id, 'tooth_number' => '36', 'surfaces' => ['O'], 'qty' => 1, 'operator_id' => $this->doctor->id]],
    ])->call('save')->assertHasNoFormErrors();

    $visit->refresh()->load('medicalRecord.diagnoses', 'procedures');
    expect($visit->medicalRecord->vitals['blood_pressure'])->toBe('120/80')
        ->and($visit->medicalRecord->diagnoses)->toHaveCount(1)
        ->and($visit->procedures->first())->surfaces->toBe('O')->price_snapshot->toBe(400_000);

    Livewire::test(ExamineVisit::class, ['record' => $visit->getRouteKey()])
        ->callAction('sign', data: ['password' => 'password'])
        ->assertHasNoActionErrors();

    $visit->refresh();
    expect($visit->isLocked())->toBeTrue()->and($visit->status)->toBe(VisitStatus::Done);

    Livewire::test(ExamineVisit::class, ['record' => $visit->getRouteKey()])
        ->assertActionHidden('sign')
        ->assertFormFieldIsDisabled('medicalRecord.subjective')
        ->call('save')
        ->assertForbidden();
});

it('perawat bisa mengisi draf tetapi tidak bisa menandatangani', function () {
    $visit = Visit::factory()->inTreatment()->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id]);
    staff('perawat');

    Livewire::test(ExamineVisit::class, ['record' => $visit->getRouteKey()])
        ->fillForm(['medicalRecord.subjective' => 'Keluhan dicatat perawat'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertActionHidden('sign');

    expect($visit->medicalRecord()->value('subjective'))->toBe('Keluhan dicatat perawat');
});

it('menyamarkan nama pasien di papan antrean', function () {
    expect(QueueBoard::maskName('Siti Aminah Putri'))->toBe('Siti A. P.')
        ->and(QueueBoard::maskName('Budi'))->toBe('Budi');

    $visit = Visit::factory()->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id, 'patient_id' => Patient::factory()->create(['name' => 'Joko Saputra'])->id]);
    staff('resepsionis');

    Livewire::test(QueueBoard::class)->assertSee('Joko S.')->assertDontSee('Saputra');
    expect($visit->status)->toBe(VisitStatus::Waiting);
});

it('memuat permukaan gigi tersimpan ke tombol M O D V L saat form dibuka lagi', function () {
    $plan = TreatmentPlan::create([
        'patient_id' => Patient::factory()->create()->id, 'practitioner_id' => $this->doctor->id,
        'title' => 'Rencana', 'status' => TreatmentPlanStatus::Draft,
    ]);
    $plan->items()->create(['treatment_id' => Treatment::factory()->create()->id, 'tooth_number' => '37', 'surfaces' => 'MO', 'price_estimate' => 400_000]);
    staff('dokter', $this->doctor->user);

    $state = Livewire::test(EditTreatmentPlan::class, ['record' => $plan->getRouteKey()])
        ->instance()->form->getRawState();

    expect(array_values($state['items'])[0]['surfaces'])->toBe(['M', 'O']);
});
