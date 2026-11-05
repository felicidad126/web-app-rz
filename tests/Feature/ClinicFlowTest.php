<?php

use App\Enums\AppointmentStatus;
use App\Enums\OdontogramEntryType;
use App\Enums\RecallStatus;
use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Enums\VisitStatus;
use App\Exceptions\MedicalRecordLockedException;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Chair;
use App\Models\OdontogramEntry;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\PractitionerSchedule;
use App\Models\Recall;
use App\Models\ScheduleException;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\Visit;
use App\Services\Clinic\AppointmentAvailability;
use App\Services\Clinic\ConsentRecorder;
use App\Services\Clinic\MedicalRecordSigning;
use App\Services\Clinic\OdontogramChart;
use App\Services\Clinic\VisitFlow;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 08:00'));
    $this->branch = Branch::factory()->create();
    $this->chair = Chair::create(['branch_id' => $this->branch->id, 'name' => 'Unit 1']);
    $this->doctor = Practitioner::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

function slot(string $from, string $to): array
{
    return [today()->setTimeFromTimeString($from), today()->setTimeFromTimeString($to)];
}

it('mendeteksi bentrok dokter, kursi, dan pasien, tetapi mengabaikan janji batal & yang bersebelahan', function () {
    $patient = Patient::factory()->create();
    Appointment::factory()->at('09:00', 45)->create([
        'branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id, 'chair_id' => $this->chair->id, 'patient_id' => $patient->id,
    ]);
    Appointment::factory()->at('10:00')->create([
        'branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id, 'status' => AppointmentStatus::Cancelled,
    ]);

    $other = Patient::factory()->create();

    expect(AppointmentAvailability::conflicts($this->doctor->id, $this->chair->id, $other->id, ...slot('09:30', '10:00')))
        ->toHaveCount(2)
        ->and(AppointmentAvailability::conflicts(Practitioner::factory()->create()->id, null, $patient->id, ...slot('09:15', '09:30')))
        ->toHaveCount(1)
        ->and(AppointmentAvailability::conflicts($this->doctor->id, $this->chair->id, $other->id, ...slot('09:45', '10:30')))
        ->toBeEmpty();
});

it('menolak janji di luar jadwal praktik atau saat dokter cuti', function () {
    PractitionerSchedule::create([
        'practitioner_id' => $this->doctor->id, 'branch_id' => $this->branch->id,
        'day_of_week' => 4, 'start_time' => '08:00', 'end_time' => '12:00',
    ]);

    expect(AppointmentAvailability::scheduleIssue($this->doctor->id, $this->branch->id, ...slot('09:00', '09:30')))->toBeNull()
        ->and(AppointmentAvailability::scheduleIssue($this->doctor->id, $this->branch->id, ...slot('11:45', '12:15')))->toContain('luar jadwal');

    ScheduleException::create(['practitioner_id' => $this->doctor->id, 'date' => today(), 'start_time' => '09:00', 'end_time' => '10:00', 'reason' => 'Seminar']);

    expect(AppointmentAvailability::scheduleIssue($this->doctor->id, $this->branch->id, ...slot('09:30', '10:00')))->toContain('Seminar')
        ->and(AppointmentAvailability::scheduleIssue($this->doctor->id, $this->branch->id, ...slot('10:00', '10:30')))->toBeNull();
});

it('check-in membuat kunjungan dengan nomor antrean per cabang per hari', function () {
    $first = Appointment::factory()->at('09:00')->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id]);
    $second = Appointment::factory()->at('09:30')->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id]);

    $visitA = VisitFlow::checkIn($first);
    $visitB = VisitFlow::checkIn($second);
    $walkIn = VisitFlow::walkIn(['branch_id' => Branch::factory()->create()->id, 'patient_id' => Patient::factory()->create()->id, 'practitioner_id' => $this->doctor->id]);

    expect($first->fresh()->status)->toBe(AppointmentStatus::Arrived)
        ->and($visitA->queue_number)->toBe(1)
        ->and($visitB->queue_number)->toBe(2)
        ->and($walkIn->queue_number)->toBe(1)
        ->and($walkIn->is_walk_in)->toBeTrue()
        ->and($visitA->visit_number)->toBe('KJ-2611-0001')
        ->and($walkIn->visit_number)->toBe('KJ-2611-0003')
        ->and(fn () => VisitFlow::checkIn($first->fresh()))->toThrow(InvalidArgumentException::class);
});

it('mengunci rekam medis setelah ditandatangani; koreksi hanya lewat addendum', function () {
    $this->seed(ReferenceDataSeeder::class);
    $user = loginAs('dokter');
    $visit = Visit::factory()->inTreatment()->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id]);
    $record = $visit->ensureMedicalRecord();

    expect(MedicalRecordSigning::problems($visit->fresh()))->toHaveCount(2);

    $record->update(['subjective' => 'Ngilu gigi kiri bawah']);
    $record->diagnoses()->create(['icd10_code' => 'K02.1', 'tooth_number' => '36']);

    MedicalRecordSigning::sign($visit->fresh(), $user);

    $visit->refresh();
    expect($visit->status)->toBe(VisitStatus::Done)
        ->and($visit->isLocked())->toBeTrue()
        ->and($visit->medicalRecord->signed_by)->toBe($user->id)
        ->and(fn () => $visit->medicalRecord->update(['subjective' => 'diubah']))->toThrow(MedicalRecordLockedException::class)
        ->and(fn () => $visit->medicalRecord->diagnoses()->create(['icd10_code' => 'K02.1']))->toThrow(MedicalRecordLockedException::class)
        ->and(fn () => $visit->medicalRecord->diagnoses()->first()->delete())->toThrow(MedicalRecordLockedException::class);

    $visit->medicalRecord->addenda()->create(['content' => 'Koreksi: gigi 37, bukan 36.']);
    expect($visit->medicalRecord->addenda()->count())->toBe(1);
});

it('menandai item rencana yang dikerjakan dan membuat recall saat ditandatangani', function () {
    $this->seed(ReferenceDataSeeder::class);
    $user = loginAs('dokter');
    $scaling = Treatment::factory()->create(['base_price' => 350_000, 'recall_months' => 6, 'icd9cm_code' => null]);
    $filling = Treatment::factory()->create(['base_price' => 400_000, 'icd9cm_code' => null]);
    $visit = Visit::factory()->inTreatment()->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id]);

    $plan = TreatmentPlan::create(['patient_id' => $visit->patient_id, 'practitioner_id' => $this->doctor->id, 'title' => 'Rencana', 'status' => TreatmentPlanStatus::Accepted]);
    $itemScaling = $plan->items()->create(['treatment_id' => $scaling->id, 'price_estimate' => 350_000]);
    $plan->items()->create(['treatment_id' => $filling->id, 'tooth_number' => '36', 'price_estimate' => 400_000]);

    $record = $visit->ensureMedicalRecord();
    $record->update(['subjective' => 'Kontrol']);
    $record->diagnoses()->create(['icd10_code' => 'K03.6']);
    $procedure = $visit->procedures()->create(['treatment_id' => $scaling->id, 'operator_id' => $this->doctor->id, 'treatment_plan_item_id' => $itemScaling->id]);

    expect($procedure->price_snapshot)->toBe(350_000);

    MedicalRecordSigning::sign($visit->fresh(), $user);

    expect($itemScaling->fresh()->status)->toBe(TreatmentPlanItemStatus::Done)
        ->and($plan->fresh()->status)->toBe(TreatmentPlanStatus::PartiallyDone)
        ->and(Recall::where('patient_id', $visit->patient_id)->first())
        ->status->toBe(RecallStatus::Pending)
        ->due_date->toDateString()->toBe('2027-05-05');
});

it('menghitung odontogram terkini dan DMF-T dari riwayat', function () {
    $this->seed(ReferenceDataSeeder::class);
    $patient = Patient::factory()->create();
    $add = fn (string $tooth, ?string $surface, string $code, OdontogramEntryType $type = OdontogramEntryType::Existing) => OdontogramEntry::create([
        'patient_id' => $patient->id, 'tooth_number' => $tooth, 'surface' => $surface, 'condition_code' => $code, 'entry_type' => $type,
    ]);

    $add('16', 'O', 'cof');
    $add('46', null, 'mis');
    $add('18', null, 'rrx');
    $add('36', 'O', 'car');
    $add('36', 'O', 'cof', OdontogramEntryType::Planned);
    $add('36', 'O', 'cof', OdontogramEntryType::Done);
    $add('37', 'M', 'car');
    $add('55', null, 'car');

    $chart = new OdontogramChart($patient);
    $teeth = $chart->teeth();

    expect(collect($teeth['36']['current'])->pluck('condition_code')->all())->toBe(['cof'])
        ->and($teeth['36']['planned'])->toBeEmpty()
        ->and($chart->indices())->toMatchArray(['D' => 2, 'M' => 1, 'F' => 2, 'total' => 5, 'd' => 1]);
});

it('menyimpan informed consent dengan tanda tangan & PDF di disk privat', function () {
    Storage::fake('local');
    loginAs('dokter');
    $visit = Visit::factory()->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id]);
    $png = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

    $consent = ConsentRecorder::record($visit, [
        'practitioner_id' => $this->doctor->id, 'title' => 'Persetujuan cabut gigi', 'content' => 'Saya menyetujui tindakan.',
        'signer_name' => $visit->patient->name, 'signer_relation' => 'self', 'signature' => $png,
    ]);

    Storage::disk('local')->assertExists($consent->signature_path);
    Storage::disk('local')->assertExists($consent->pdf_path);
    expect(fn () => $consent->update(['content_snapshot' => 'diubah']))->toThrow(LogicException::class)
        ->and(fn () => ConsentRecorder::record($visit, [
            'practitioner_id' => $this->doctor->id, 'title' => 'x', 'content' => 'x', 'signer_name' => 'x', 'signer_relation' => 'self', 'signature' => '',
        ]))->toThrow(InvalidArgumentException::class);
});
