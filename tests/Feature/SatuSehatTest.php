<?php

use App\Enums\ItemType;
use App\Enums\SatuSehatIssue;
use App\Enums\SatuSehatSyncStatus;
use App\Filament\Pages\SatuSehatMonitor;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Jobs\SendVisitToSatuSehat;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\SatuSehatSync;
use App\Models\Treatment;
use App\Models\Unit;
use App\Models\Visit;
use App\Services\Clinic\MedicalRecordSigning;
use App\Services\SatuSehat\LocationRegistrar;
use App\Services\SatuSehat\SatuSehatClient;
use App\Services\SatuSehat\SatuSehatException;
use App\Services\SatuSehat\VisitSync;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

const SS_FHIR = 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 11:00'));
    $this->seed(ReferenceDataSeeder::class);
    config([
        'satusehat.enabled' => true,
        'satusehat.environment' => 'sandbox',
        'satusehat.organization_id' => 'ORG-100',
        'satusehat.client_id' => 'client',
        'satusehat.client_secret' => 'secret',
    ]);
    $this->branch = Branch::factory()->create(['code' => 'KMG', 'satusehat_location_id' => 'LOC-1']);
    $this->doctor = Practitioner::factory()->create();
    $this->patient = Patient::factory()->create(['nik' => '3171012345678901']);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Tiruan API SATUSEHAT. $bundleReplies: balasan berurutan untuk POST Bundle, [status, body|null];
 * kosong = sukses (transaction-response dibuat dari isi permintaan).
 *
 * @param  list<array{0: int, 1: array<string, mixed>|null}>  $bundleReplies
 */
function fakeSatuSehat(array $bundleReplies = [], bool $patientFound = true): void
{
    Http::fake(function (Request $request) use (&$bundleReplies, $patientFound) {
        $url = $request->url();

        if (str_contains($url, '/oauth2/v1/accesstoken')) {
            return Http::response(['access_token' => 'tok-'.uniqid(), 'expires_in' => '14399']);
        }

        if (str_contains($url, '/Patient?')) {
            return Http::response(['resourceType' => 'Bundle', 'total' => $patientFound ? 1 : 0, 'entry' => $patientFound ? [['resource' => ['resourceType' => 'Patient', 'id' => 'P02478375538']]] : []]);
        }

        if (str_contains($url, '/Practitioner?')) {
            return Http::response(['resourceType' => 'Bundle', 'entry' => [['resource' => ['resourceType' => 'Practitioner', 'id' => '10009880728']]]]);
        }

        if (str_contains($url, '/Organization/')) {
            return Http::response(['resourceType' => 'Organization', 'id' => 'ORG-100', 'name' => 'Klinik Senyum Dental']);
        }

        if (rtrim($url, '/') === SS_FHIR && $request->method() === 'POST') {
            if ($bundleReplies !== []) {
                [$status, $body] = array_shift($bundleReplies);

                return Http::response($body ?? [], $status);
            }

            $entries = array_map(fn (array $entry, int $i): array => ['response' => [
                'location' => $entry['request']['url'].'/id-'.$i.'/_history/1',
                'resourceType' => $entry['request']['url'],
                'status' => '201 Created',
            ]], $request->data()['entry'], array_keys($request->data()['entry']));

            return Http::response(['resourceType' => 'Bundle', 'type' => 'transaction-response', 'entry' => $entries]);
        }

        return Http::response(['message' => 'not faked: '.$url], 404);
    });
}

/** Kunjungan bertanda tangan: S + diagnosis + tanda vital + 1 tindakan + resep (1 obat ber-KFA, 1 tanpa). */
function signedVisit(?Patient $patient = null, ?string $icd9 = '23.2'): Visit
{
    $visit = Visit::factory()->inTreatment()->create([
        'branch_id' => test()->branch->id, 'patient_id' => ($patient ?? test()->patient)->id, 'practitioner_id' => test()->doctor->id,
        'arrived_at' => now()->subHour(),
    ]);
    $record = $visit->ensureMedicalRecord();
    $record->update(['subjective' => 'Ngilu', 'vitals' => ['blood_pressure' => '120/80', 'pulse' => '84', 'temperature' => '36.7']]);
    $record->diagnoses()->create(['icd10_code' => 'K02.1', 'type' => 'primary', 'tooth_number' => '36']);
    $visit->procedures()->create(['treatment_id' => Treatment::factory()->create(['icd9cm_code' => $icd9])->id, 'tooth_number' => '36', 'surfaces' => 'O', 'operator_id' => test()->doctor->id]);

    $tablet = Unit::firstOrCreate(['name' => 'tablet']);
    $withKfa = Item::create(['sku' => 'A'.$visit->id, 'name' => 'Asam mefenamat 500 mg', 'type' => ItemType::Medicine, 'base_unit_id' => $tablet->id, 'kfa_code' => '93001234']);
    $withoutKfa = Item::create(['sku' => 'B'.$visit->id, 'name' => 'Obat kumur racikan', 'type' => ItemType::Medicine, 'base_unit_id' => $tablet->id]);
    $prescription = $visit->prescription()->create(['practitioner_id' => test()->doctor->id]);
    $prescription->items()->create(['item_id' => $withKfa->id, 'qty' => 10, 'unit_id' => $tablet->id, 'signa' => '3×1 sesudah makan']);
    $prescription->items()->create(['item_id' => $withoutKfa->id, 'qty' => 1, 'unit_id' => $tablet->id, 'signa' => 'kumur 2×']);

    Queue::fake();
    MedicalRecordSigning::sign($visit->fresh(), test()->doctor->user);

    return $visit->fresh();
}

function bundleRequest(): ?Request
{
    return Http::recorded()->map(fn (array $pair): Request => $pair[0])
        ->first(fn (Request $r): bool => rtrim($r->url(), '/') === SS_FHIR && $r->method() === 'POST');
}

it('mengantrekan kunjungan ke SATUSEHAT setelah rekam medis ditandatangani', function () {
    $visit = signedVisit();

    Queue::assertPushed(SendVisitToSatuSehat::class, fn (SendVisitToSatuSehat $job): bool => $job->visitId === $visit->id);
    expect(SatuSehatSync::first())->status->toBe(SatuSehatSyncStatus::Pending)->branch_id->toBe($this->branch->id);
});

it('mengirim kunjungan sebagai satu Bundle transaksi dan menyimpan ID dari SATUSEHAT', function () {
    $visit = signedVisit();
    fakeSatuSehat();

    $sync = app(VisitSync::class)->send($visit);
    $entries = collect(bundleRequest()->data()['entry']);
    $resource = fn (string $type) => $entries->firstWhere('request.url', $type)['resource'];

    expect($sync->status)->toBe(SatuSehatSyncStatus::Sent)
        ->and($sync->satusehat_id)->toBe('id-0')
        ->and($sync->resource_counts)->toBe(['Encounter' => 1, 'Condition' => 1, 'Observation' => 4, 'Procedure' => 1, 'Medication' => 1, 'MedicationRequest' => 1])
        ->and($sync->warnings)->toBe(['Obat "Obat kumur racikan" tidak dikirim: belum punya kode KFA.'])
        ->and($sync->resource_ids['Condition'])->toHaveCount(1)
        ->and($this->patient->fresh()->satusehat_ihs_id)->toBe('P02478375538')
        ->and($this->doctor->fresh()->satusehat_ihs_id)->toBe('10009880728');

    $encounter = $resource('Encounter');
    expect(bundleRequest()->data())->toMatchArray(['resourceType' => 'Bundle', 'type' => 'transaction'])
        ->and($encounter['identifier'][0])->toBe(['system' => 'http://sys-ids.kemkes.go.id/encounter/ORG-100', 'value' => $visit->visit_number])
        ->and($encounter['status'])->toBe('finished')
        ->and($encounter['class']['code'])->toBe('AMB')
        ->and($encounter['subject']['reference'])->toBe('Patient/P02478375538')
        ->and($encounter['location'][0]['location']['reference'])->toBe('Location/LOC-1')
        ->and(collect($encounter['statusHistory'])->pluck('status')->all())->toBe(['arrived', 'in-progress', 'finished'])
        ->and($encounter['period']['start'])->toEndWith('+00:00')
        ->and($encounter['diagnosis'][0]['condition']['reference'])->toBe($entries->firstWhere('request.url', 'Condition')['fullUrl'])
        ->and($resource('Condition')['code']['coding'][0])->toMatchArray(['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => 'K02.1'])
        ->and($resource('Procedure')['code']['coding'][0])->toMatchArray(['system' => 'http://hl7.org/fhir/sid/icd-9-cm', 'code' => '23.2'])
        ->and($resource('Procedure')['encounter']['reference'])->toBe($entries->first()['fullUrl'])
        ->and($resource('Medication')['code']['coding'][0])->toMatchArray(['system' => 'http://sys-ids.kemkes.go.id/kfa', 'code' => '93001234'])
        ->and($resource('MedicationRequest')['medicationReference']['reference'])->toBe($entries->firstWhere('request.url', 'Medication')['fullUrl']);

    $loinc = $entries->where('request.url', 'Observation')->map(fn ($e) => [$e['resource']['code']['coding'][0]['code'], $e['resource']['valueQuantity']['value']])->values()->all();
    expect($loinc)->toBe([['8480-6', 120.0], ['8462-4', 80.0], ['8867-4', 84.0], ['8310-5', 36.7]]);

    // Kirim ulang tidak membuat data ganda.
    $before = Http::recorded()->count();
    app(VisitSync::class)->send($visit);
    expect(Http::recorded()->count())->toBe($before);
});

it('menahan kunjungan dengan panduan perbaikan bila data belum lengkap, tanpa mengirim apa pun', function (string $case, SatuSehatIssue $issue) {
    $patient = $case === 'nik' ? Patient::factory()->withoutNik()->create() : $this->patient;
    $visit = signedVisit($patient, $case === 'icd9' ? null : '23.2');

    if ($case === 'location') {
        $this->branch->update(['satusehat_location_id' => null]);
    }

    fakeSatuSehat(patientFound: $case !== 'notfound');

    expect(fn () => app(VisitSync::class)->send($visit))->toThrow(SatuSehatException::class);

    expect(SatuSehatSync::first())->status->toBe(SatuSehatSyncStatus::Blocked)->issue_code->toBe($issue)
        ->and(bundleRequest())->toBeNull();
})->with([
    'NIK pasien kosong' => ['nik', SatuSehatIssue::PatientNikMissing],
    'NIK tidak ditemukan' => ['notfound', SatuSehatIssue::PatientNotFound],
    'tindakan tanpa ICD-9-CM' => ['icd9', SatuSehatIssue::ProcedureCodeMissing],
    'cabang tanpa Location' => ['location', SatuSehatIssue::LocationMissing],
]);

it('gangguan server dicoba ulang oleh queue; penolakan validasi menunggu perbaikan dan tidak membocorkan NIK', function () {
    $visit = signedVisit();
    fakeSatuSehat([
        [503, null],
        [400, ['resourceType' => 'OperationOutcome', 'issue' => [
            ['severity' => 'error', 'code' => 'invalid', 'details' => ['text' => 'Patient 3171012345678901 period.start is invalid'], 'expression' => ['Encounter.period']],
        ]]],
        [400, ['resourceType' => 'OperationOutcome', 'issue' => [['severity' => 'error', 'code' => 'duplicate', 'details' => ['text' => 'Found duplicate resource']]]]],
    ]);

    expect(fn () => (new SendVisitToSatuSehat($visit->id))->handle(app(VisitSync::class)))->toThrow(SatuSehatException::class);
    expect(SatuSehatSync::first())->status->toBe(SatuSehatSyncStatus::Failed)->issue_code->toBe(SatuSehatIssue::Unreachable)->attempts->toBe(1);

    // Masalah data tidak dilempar ulang (tidak diulang otomatis).
    (new SendVisitToSatuSehat($visit->id))->handle(app(VisitSync::class));

    $sync = SatuSehatSync::first();
    expect($sync->status)->toBe(SatuSehatSyncStatus::Blocked)
        ->and($sync->issue_code)->toBe(SatuSehatIssue::Rejected)
        ->and($sync->last_error)->toContain('period.start is invalid [Encounter.period]')->not->toContain('3171012345678901')->toContain('[NIK]');

    // Kiriman yang ternyata sudah ada (duplikat) bisa ditandai diterima oleh admin.
    (new SendVisitToSatuSehat($visit->id))->handle(app(VisitSync::class));
    expect(SatuSehatSync::first()->issue_code)->toBe(SatuSehatIssue::Duplicate);

    $admin = loginAs('admin');
    $admin->branches()->sync([$this->branch->id]);

    Livewire::test(SatuSehatMonitor::class)
        ->assertSee('Sudah ada di SATUSEHAT')
        ->callAction('markSent', arguments: ['sync' => SatuSehatSync::first()->id]);

    expect(SatuSehatSync::first()->status)->toBe(SatuSehatSyncStatus::Sent);
});

it('menyimpan token, dan mengambil token baru sekali bila ditolak 401', function () {
    $calls = ['token' => 0, 'org' => 0];
    Http::fake(function (Request $request) use (&$calls) {
        if (str_contains($request->url(), 'accesstoken')) {
            $calls['token']++;

            return Http::response(['access_token' => 'tok'.$calls['token'], 'expires_in' => '3599']);
        }

        $calls['org']++;

        return $calls['org'] === 2 ? Http::response([], 401) : Http::response(['id' => 'ORG-100']);
    });

    $client = SatuSehatClient::fromConfig();
    $client->get('Organization/ORG-100');
    $client->get('Organization/ORG-100');

    expect($calls)->toBe(['token' => 2, 'org' => 3]);
});

it('halaman SATUSEHAT: admin melihat yang perlu diperbaiki dan mengirim ulang; kasir tidak boleh membuka', function () {
    $visit = signedVisit(Patient::factory()->withoutNik()->create());
    fakeSatuSehat();
    rescue(fn () => app(VisitSync::class)->send($visit), report: false);

    $admin = loginAs('admin');
    $admin->branches()->sync([$this->branch->id]);
    Queue::fake();

    Livewire::test(SatuSehatMonitor::class)
        ->assertSee('Pasien belum punya NIK')
        ->assertSee('Lengkapi NIK')
        ->callAction('testConnection')
        ->assertNotified('Terhubung ke SATUSEHAT')
        ->callAction('resend', arguments: ['sync' => SatuSehatSync::first()->id])
        ->assertNotified('1 data masuk antrean kirim ulang');

    Queue::assertPushed(SendVisitToSatuSehat::class);
    expect(SatuSehatSync::first()->status)->toBe(SatuSehatSyncStatus::Pending);

    loginAs('kasir');
    $this->get('/satusehat')->assertForbidden();
});

it('backfill mengantrekan kunjungan lama yang belum terkirim', function () {
    signedVisit();
    $sent = signedVisit(Patient::factory()->create());
    SatuSehatSync::where('syncable_id', $sent->id)->update(['status' => SatuSehatSyncStatus::Sent]);
    Queue::fake();

    $this->artisan('satusehat:backfill', ['since' => '2026-11-01'])->expectsOutputToContain('1 kunjungan')->assertSuccessful();
    Queue::assertPushed(SendVisitToSatuSehat::class, 1);
});

it('tidak mengirim apa pun saat bridging dimatikan', function () {
    config(['satusehat.enabled' => false]);
    signedVisit();

    Queue::assertNothingPushed();
    expect(SatuSehatSync::count())->toBe(0);
});

it('mendaftarkan Location cabang dan mencari IHS pasien dari halaman', function () {
    Http::fake([
        '*/oauth2/v1/accesstoken*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
        '*/fhir-r4/v1/Location' => Http::response(['resourceType' => 'Location', 'id' => 'b017aa54-f1df-4ec2-9d84-8823815d7228'], 201),
        '*/fhir-r4/v1/Patient*' => Http::response(['entry' => [['resource' => ['id' => 'P1234']]]]),
    ]);
    $branch = Branch::factory()->create(['satusehat_location_id' => null]);

    expect(app(LocationRegistrar::class)->register($branch))->toBe('b017aa54-f1df-4ec2-9d84-8823815d7228')
        ->and($branch->fresh()->satusehat_location_id)->toBe('b017aa54-f1df-4ec2-9d84-8823815d7228');

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/Location')
        && $r['identifier'][0]['system'] === 'http://sys-ids.kemkes.go.id/location/ORG-100'
        && $r['managingOrganization']['reference'] === 'Organization/ORG-100');

    $user = loginAs('resepsionis');
    $user->branches()->sync([$this->branch->id]);

    Livewire::test(ViewPatient::class, ['record' => $this->patient->getRouteKey()])
        ->callAction('lookupIhs')
        ->assertNotified('IHS pasien ditemukan: P1234');

    expect($this->patient->fresh()->satusehat_ihs_id)->toBe('P1234');
    Http::assertSent(fn (Request $r): bool => str_contains(urldecode($r->url()), 'identifier=https://fhir.kemkes.go.id/id/nik|3171012345678901'));
});

it('membatasi halaman SATUSEHAT sesuai peran', function (string $role, int $status) {
    loginAs($role);

    $this->get('/satusehat')->assertStatus($status);
})->with([
    ['admin', 200], ['owner', 200], ['resepsionis', 200], ['perawat', 200], ['dokter', 403], ['kasir', 403],
]);
