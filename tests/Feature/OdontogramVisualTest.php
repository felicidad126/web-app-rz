<?php

use App\Enums\OdontogramEntryType;
use App\Exceptions\MedicalRecordLockedException;
use App\Filament\Resources\Patients\Pages\PatientOdontogram;
use App\Filament\Resources\Visits\Pages\ExamineVisit;
use App\Filament\Resources\Visits\RelationManagers\OdontogramEntriesRelationManager;
use App\Models\Branch;
use App\Models\OdontogramEntry;
use App\Models\OralExamination;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\User;
use App\Models\Visit;
use App\Services\Clinic\MedicalRecordSigning;
use App\Services\Clinic\OdontogramChart;
use App\Services\Clinic\OdontogramVisual;
use App\Services\Clinic\OralHygiene;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:10'));
    $this->seed(ReferenceDataSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->doctor = Practitioner::factory()->create();
    $this->doctorUser = User::findOrFail($this->doctor->user_id);
    $this->doctorUser->assignRole('dokter');
    $this->doctorUser->branches()->sync([$this->branch->id]);
    $this->patient = Patient::factory()->create();
    $this->visit = Visit::factory()->inTreatment()->create(['branch_id' => $this->branch->id, 'patient_id' => $this->patient->id, 'practitioner_id' => $this->doctor->id]);
});

afterEach(fn () => Carbon::setTestNow());

it('memetakan bagian gambar ke permukaan FDI sesuai kuadran & rahang', function (string $tooth, array $expected) {
    expect(OdontogramVisual::surfaceMap($tooth))->toBe($expected);
})->with([
    'molar kanan atas' => ['16', ['t' => 'V', 'b' => 'L', 'l' => 'D', 'r' => 'M', 'c' => 'O']],
    'molar kiri atas' => ['26', ['t' => 'V', 'b' => 'L', 'l' => 'M', 'r' => 'D', 'c' => 'O']],
    'molar kiri bawah' => ['36', ['t' => 'L', 'b' => 'V', 'l' => 'M', 'r' => 'D', 'c' => 'O']],
    'molar kanan bawah' => ['46', ['t' => 'L', 'b' => 'V', 'l' => 'D', 'r' => 'M', 'c' => 'O']],
    'sulung kanan atas' => ['55', ['t' => 'V', 'b' => 'L', 'l' => 'D', 'r' => 'M', 'c' => 'O']],
    'sulung kiri bawah' => ['75', ['t' => 'L', 'b' => 'V', 'l' => 'M', 'r' => 'D', 'c' => 'O']],
]);

it('menggambar status terkini, rencana, gigi hilang, mahkota, dan yang dikerjakan di kunjungan ini', function () {
    $entry = fn (array $a) => OdontogramEntry::create(['patient_id' => $this->patient->id, 'entry_type' => OdontogramEntryType::Existing, ...$a]);
    $entry(['tooth_number' => '16', 'surface' => 'O', 'condition_code' => 'amf']);
    $entry(['tooth_number' => '46', 'condition_code' => 'mis']);
    $entry(['tooth_number' => '26', 'condition_code' => 'poc']);
    $entry(['tooth_number' => '37', 'surface' => 'M', 'condition_code' => 'car', 'entry_type' => OdontogramEntryType::Planned]);
    $entry(['tooth_number' => '36', 'surface' => 'D', 'condition_code' => 'cof', 'entry_type' => OdontogramEntryType::Done, 'visit_id' => $this->visit->id]);

    $data = (new OdontogramVisual(new OdontogramChart($this->patient), $this->visit))->data();
    $t = $data['teeth'];

    expect($t['16']['fill']['c'])->toBe('#344054')
        ->and($t['46']['mark'])->toBe('mis')
        ->and($t['26']['ring'])->not->toBeNull()
        ->and($t['37'])->plan->toBeTrue()->and($t['37']['fill']['l'])->toBe(OdontogramVisual::PLAN_FILL)
        ->and($t['36'])->here->toBeTrue()->and($t['36']['fill']['r'])->toBe(OdontogramVisual::DONE_HERE)
        ->and($t['36']['info'][0]['text'])->toStartWith('Kunjungan ini:')
        ->and($data['indices'])->toMatchArray(['M' => 1, 'F' => 3]) // amalgam 16, mahkota 26, komposit 36
        ->and(collect($data['legend'])->pluck('label'))->toContain('Rencana', 'Dikerjakan di kunjungan ini')
        ->and((new OdontogramVisual(new OdontogramChart($this->patient)))->svg())->toStartWith('<svg');
});

it('dokter mencatat dari odontogram visual: satu baris per permukaan, kode seluruh gigi tanpa permukaan', function () {
    $this->actingAs($this->doctorUser);
    $rm = Livewire::test(OdontogramEntriesRelationManager::class, ['ownerRecord' => $this->visit, 'pageClass' => ExamineVisit::class])
        ->assertOk()->assertSee('Odontogram');

    $result = $rm->instance()->saveOdontogramFromChart('36', ['O', 'D', 'X'], 'cof', 'done', 'Komposit A2');
    expect($result['ok'])->toBeTrue()
        ->and(OdontogramEntry::where('tooth_number', '36')->pluck('surface')->map->value->sort()->values()->all())->toBe(['D', 'O'])
        ->and(OdontogramEntry::where('tooth_number', '36')->first())->visit_id->toBe($this->visit->id)->notes->toBe('Komposit A2')
        ->and($result['chart']['teeth']['36']['here'])->toBeTrue();

    $rm->instance()->saveOdontogramFromChart('46', ['O'], 'mis', 'existing');
    expect(OdontogramEntry::where('tooth_number', '46')->sole()->surface)->toBeNull();

    expect($rm->instance()->saveOdontogramFromChart('19', ['O'], 'car', 'existing')['ok'])->toBeFalse()
        ->and($rm->instance()->saveOdontogramFromChart('36', [], 'car', 'existing')['ok'])->toBeFalse()
        ->and($rm->instance()->saveOdontogramFromChart('36', ['O'], 'xyz', 'existing')['ok'])->toBeFalse();
});

it('menolak pencatatan setelah rekam medis ditandatangani atau tanpa izin ubah', function () {
    $this->actingAs($this->doctorUser);
    $record = $this->visit->ensureMedicalRecord();
    $record->update(['subjective' => 'Kontrol']);
    $record->diagnoses()->create(['icd10_code' => 'K02.1']);
    MedicalRecordSigning::sign($this->visit->fresh(), $this->doctorUser);

    $rm = Livewire::test(OdontogramEntriesRelationManager::class, ['ownerRecord' => $this->visit->fresh(), 'pageClass' => ExamineVisit::class]);
    expect($rm->instance()->saveOdontogramFromChart('36', ['O'], 'car', 'existing')['ok'])->toBeFalse()
        ->and(OdontogramEntry::count())->toBe(0);

    $owner = User::factory()->withMfa()->create();
    $owner->assignRole('owner');
    $this->actingAs($owner);
    $page = Livewire::test(PatientOdontogram::class, ['record' => $this->patient->getRouteKey()])->assertOk();
    expect($page->instance()->saveOdontogramFromChart('36', ['O'], 'car', 'existing')['ok'])->toBeFalse();
});

it('halaman pasien mencatat kondisi awal tanpa kunjungan; "dikerjakan" hanya dari kunjungan', function () {
    $this->actingAs($this->doctorUser);
    $page = Livewire::test(PatientOdontogram::class, ['record' => $this->patient->getRouteKey()])->assertOk()->assertSee('kondisi awal');

    expect($page->instance()->saveOdontogramFromChart('11', ['V'], 'cfr', 'existing')['ok'])->toBeTrue()
        ->and(OdontogramEntry::sole())->visit_id->toBeNull()
        ->and($page->instance()->saveOdontogramFromChart('11', ['O'], 'cof', 'done')['ok'])->toBeFalse();

    $this->get(route('clinic.odontogram.pdf', $this->patient))->assertOk();
});

it('menghitung OHI-S (Greene & Vermillion) dan kategorinya', function (array $scores, ?float $di, ?float $ci, ?float $ohis, ?string $category) {
    expect(OralHygiene::calculate($scores))->toBe(['debris_index' => $di, 'calculus_index' => $ci, 'ohis' => $ohis, 'category' => $category]);
})->with([
    'semua 1 / 0' => [array_fill_keys(['16', '11', '26', '36', '31', '46'], ['debris' => 1, 'calculus' => 0]), 1.0, 0.0, 1.0, 'Baik'],
    'campuran' => [['16' => ['debris' => 1, 'calculus' => 1], '11' => ['debris' => 1, 'calculus' => 0], '26' => ['debris' => 0, 'calculus' => 0],
        '36' => ['debris' => 1, 'calculus' => 1], '31' => ['debris' => 0, 'calculus' => 0], '46' => ['debris' => 1, 'calculus' => 0]], 0.67, 0.33, 1.0, 'Baik'],
    'gigi hilang dilewati' => [['16' => ['debris' => 2, 'calculus' => 1], '36' => ['debris' => 3, 'calculus' => 2], '46' => ['debris' => null, 'calculus' => '']], 2.5, 1.5, 4.0, 'Buruk'],
    'nilai di luar 0–3 diabaikan' => [['16' => ['debris' => 5, 'calculus' => 1], '11' => ['debris' => 2, 'calculus' => 1]], 2.0, 1.0, 3.0, 'Sedang'],
    'kosong' => [[], null, null, null, null],
]);

it('menyimpan pemeriksaan intraoral & OHI-S dari form pemeriksaan dan menguncinya saat ditandatangani', function () {
    $this->actingAs($this->doctorUser);

    Livewire::test(ExamineVisit::class, ['record' => $this->visit->getRouteKey()])
        ->assertSee('Pemeriksaan intraoral & OHI-S')
        ->fillForm([
            'medicalRecord.subjective' => 'Gusi berdarah',
            'medicalRecord.diagnoses' => [['icd10_code' => 'K05.1', 'type' => 'primary']],
            'oralExamination.occlusion' => 'normal',
            'oralExamination.torus_palatinus' => 'none',
            'oralExamination.ohis_scores' => [
                '16' => ['debris' => '2', 'calculus' => '1'], '11' => ['debris' => '1', 'calculus' => '1'], '26' => ['debris' => '2', 'calculus' => '2'],
                '36' => ['debris' => '2', 'calculus' => '1'], '31' => ['debris' => '1', 'calculus' => '1'], '46' => ['debris' => '2', 'calculus' => '2'],
            ],
        ])
        ->assertSee('OHI-S 3,00 (Sedang)')
        ->call('save')->assertHasNoFormErrors();

    $exam = OralExamination::sole();
    expect($exam)->patient_id->toBe($this->patient->id)->ohis->toBe('3.00')->debris_index->toBe('1.67')
        ->and($exam->occlusion->getLabel())->toBe('Normal bite')
        ->and($exam->category())->toBe('Sedang');

    MedicalRecordSigning::sign($this->visit->fresh(), $this->doctorUser);
    expect(fn () => $exam->fresh()->update(['diastema' => 'Ada']))->toThrow(MedicalRecordLockedException::class);

    // Tanpa isian intraoral, tidak ada baris yang dibuat.
    $other = Visit::factory()->inTreatment()->create(['branch_id' => $this->branch->id, 'practitioner_id' => $this->doctor->id]);
    Livewire::test(ExamineVisit::class, ['record' => $other->getRouteKey()])
        ->fillForm(['medicalRecord.subjective' => 'Kontrol'])->call('save')->assertHasNoFormErrors();
    expect(OralExamination::count())->toBe(1);
});
