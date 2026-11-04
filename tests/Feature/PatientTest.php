<?php

use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Models\Patient;
use App\Services\NumberSequence;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => loginAs('resepsionis'));

it('membuat nomor RM berurutan dan unik lintas cabang', function () {
    config(['clinic.rm_number.prefix' => 'RM-', 'clinic.rm_number.pad' => 6]);

    $a = Patient::factory()->create();
    $b = Patient::factory()->create();

    expect($a->rm_number)->toBe('RM-000001')
        ->and($b->rm_number)->toBe('RM-000002');
});

it('mempertahankan nomor RM yang diisi manual (migrasi data lama)', function () {
    expect(Patient::factory()->create(['rm_number' => 'LAMA-0099'])->rm_number)->toBe('LAMA-0099');
});

it('memisahkan urutan per key dan scope', function () {
    expect(NumberSequence::next('invoice', 'KMG-202611'))->toBe(1)
        ->and(NumberSequence::next('invoice', 'KMG-202611'))->toBe(2)
        ->and(NumberSequence::next('invoice', 'DPK-202611'))->toBe(1)
        ->and(NumberSequence::next('visit'))->toBe(1);
});

it('menyimpan NIK terenkripsi dengan blind index', function () {
    $patient = Patient::factory()->create(['nik' => '3171 0123 4567 8901']);
    $raw = DB::table('patients')->find($patient->id);

    expect($raw->nik)->not->toContain('3171012345678901')
        ->and($raw->nik_hash)->toHaveLength(64)
        ->and($patient->fresh()->nik)->toBe('3171012345678901')
        ->and($patient->maskedNik())->toBe('3171••••••••8901')
        ->and(Patient::whereNik('3171012345678901')->first()?->is($patient))->toBeTrue();
});

it('tidak menyimpan NIK di audit log', function () {
    $patient = Patient::factory()->create(['nik' => '3171012345678901']);
    $patient->update(['nik' => '3171012345678902', 'phone' => '081234567890']);

    $logs = Activity::query()->where('subject_type', Patient::class)->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->toJson())->not->toContain('317101234567890')
        ->and($logs->last()->properties['attributes'])->toHaveKey('phone');
});

it('bisa mendaftarkan pasien baru lewat form', function () {
    Livewire::test(CreatePatient::class)
        ->fillForm([
            'name' => 'Siti Aminah',
            'nik' => '3171012345678901',
            'birth_date' => '1990-05-17',
            'gender' => 'female',
            'phone' => '081234567890',
            'consent_data_at' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $patient = Patient::where('name', 'Siti Aminah')->firstOrFail();

    expect($patient->rm_number)->not->toBeEmpty()
        ->and($patient->consent_data_at)->not->toBeNull()
        ->and($patient->created_by)->toBe(auth()->id());
});

it('tidak mencentang persetujuan data secara otomatis di form pasien baru', function () {
    $state = Livewire::test(CreatePatient::class)->instance()->form->getRawState();

    expect($state['consent_data_at'])->toBeFalse();
});

it('menampilkan persetujuan yang sudah ada saat edit dan tidak mengubah tanggalnya', function () {
    $patient = Patient::factory()->create(['consent_data_at' => '2026-01-15 09:00:00']);

    $component = Livewire::test(EditPatient::class, ['record' => $patient->getRouteKey()]);
    expect($component->instance()->form->getRawState()['consent_data_at'])->toBeTrue();

    $component->fillForm(['phone' => '081111111111'])->call('save')->assertHasNoFormErrors();
    expect($patient->fresh()->consent_data_at->toDateTimeString())->toBe('2026-01-15 09:00:00');
});

it('menolak NIK ganda, NIK tidak valid, dan registrasi tanpa persetujuan data', function () {
    Patient::factory()->create(['nik' => '3171012345678901']);

    Livewire::test(CreatePatient::class)
        ->fillForm(['name' => 'Duplikat', 'nik' => '3171012345678901', 'birth_date' => '1990-01-01', 'gender' => 'male', 'consent_data_at' => true])
        ->call('create')
        ->assertHasFormErrors(['nik']);

    Livewire::test(CreatePatient::class)
        ->fillForm(['name' => 'Salah', 'nik' => '12345', 'birth_date' => '1990-01-01', 'gender' => 'male', 'consent_data_at' => true])
        ->call('create')
        ->assertHasFormErrors(['nik']);

    Livewire::test(CreatePatient::class)
        ->fillForm(['name' => 'Tanpa Consent', 'birth_date' => '1990-01-01', 'gender' => 'male', 'consent_data_at' => false])
        ->call('create')
        ->assertHasFormErrors(['consent_data_at']);
});

it('boleh menyimpan ulang pasien dengan NIK miliknya sendiri', function () {
    $patient = Patient::factory()->create(['nik' => '3171012345678901']);

    Livewire::test(EditPatient::class, ['record' => $patient->getRouteKey()])
        ->fillForm(['phone' => '089999999999'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($patient->fresh()->phone)->toBe('089999999999');
});

it('bisa mencari pasien dengan NIK lengkap', function () {
    $target = Patient::factory()->create(['nik' => '3171012345678901']);
    $others = Patient::factory()->count(3)->create();

    Livewire::test(ListPatients::class)
        ->searchTable('3171012345678901')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords($others);
});

it('mencatat akses saat data pasien dibuka (UU PDP)', function () {
    $patient = Patient::factory()->create();

    Livewire::test(ViewPatient::class, ['record' => $patient->getRouteKey()])->assertOk();

    expect(Activity::query()->where('log_name', 'access')->where('event', 'viewed')->where('subject_id', $patient->id)->where('causer_id', auth()->id())->exists())->toBeTrue();
});
