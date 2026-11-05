<?php

use App\Console\Commands\CheckSipExpiry;
use App\Filament\Imports\PatientImporter;
use App\Filament\Imports\TreatmentImporter;
use App\Filament\Resources\Practitioners\PractitionerResource;
use App\Models\Branch;
use App\Models\Icd10Code;
use App\Models\Item;
use App\Models\OdontogramCondition;
use App\Models\Payer;
use App\Models\Practitioner;
use App\Models\Treatment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Notifications\DatabaseNotification;

it('memakai tarif cabang jika ada, selain itu tarif dasar', function () {
    $kemang = Branch::factory()->create();
    $depok = Branch::factory()->create();
    $treatment = Treatment::factory()->create(['base_price' => 350_000]);
    $treatment->branchPrices()->create(['branch_id' => $depok->id, 'price' => 300_000]);

    expect($treatment->priceForBranch($kemang->id))->toBe(350_000)
        ->and($treatment->priceForBranch($depok->id))->toBe(300_000)
        ->and($treatment->priceForBranch(null))->toBe(350_000);
});

it('mengonversi jumlah ke satuan dasar', function () {
    $tablet = Unit::create(['name' => 'tablet']);
    $strip = Unit::create(['name' => 'strip']);
    $box = Unit::create(['name' => 'box']);
    $item = Item::create(['sku' => 'AMX', 'name' => 'Amoxicillin', 'type' => 'medicine', 'base_unit_id' => $tablet->id]);
    $item->units()->create(['unit_id' => $strip->id, 'factor_to_base' => 10]);
    $item->units()->create(['unit_id' => $box->id, 'factor_to_base' => 100]);

    expect($item->toBaseQuantity(3, $tablet->id))->toEqual(3.0)
        ->and($item->toBaseQuantity(2, $strip->id))->toEqual(20.0)
        ->and($item->toBaseQuantity(1.5, $box->id))->toEqual(150.0);
});

it('mengisi data referensi secara idempoten', function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->seed(ReferenceDataSeeder::class);

    expect(Icd10Code::where('code', 'K02.1')->count())->toBe(1)
        ->and(OdontogramCondition::where('code', 'car')->value('applies_to')->value)->toBe('surface')
        ->and(Payer::where('code', 'UMUM')->exists())->toBeTrue()
        ->and(Unit::where('name', 'tablet')->exists())->toBeTrue();
});

it('mengirim peringatan SIP ke owner dan admin', function () {
    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $kasir = User::factory()->create();
    $kasir->assignRole('kasir');

    Practitioner::factory()->sipExpiringInDays(20)->create();
    Practitioner::factory()->create(); // masih lama

    $this->artisan(CheckSipExpiry::class)->assertSuccessful();

    expect(DatabaseNotification::where('notifiable_id', $owner->id)->count())->toBe(1)
        ->and(DatabaseNotification::where('notifiable_id', $kasir->id)->count())->toBe(0);
});

it('membaca format tanggal dan jenis kelamin dari CSV sistem lama', function (string $input, ?string $expected) {
    expect(PatientImporter::parseDate($input))->toBe($expected);
})->with([
    ['17/05/1990', '1990-05-17'],
    ['17-05-1990', '1990-05-17'],
    ['1990-05-17', '1990-05-17'],
    ['', null],
]);

it('mengenali variasi penulisan jenis kelamin', function () {
    expect(PatientImporter::parseGender('L'))->toBe('male')
        ->and(PatientImporter::parseGender('Perempuan'))->toBe('female')
        ->and(PatientImporter::parseGender('wanita'))->toBe('female')
        ->and(TreatmentImporter::parseCategory('Ortodontik'))->toBe('orthodontic')
        ->and(TreatmentImporter::parseCategory(''))->toBe('other');
});

it('clinic:create-owner --check membedakan owner asli dan akun demo', function () {
    $this->artisan('clinic:create-owner', ['--check' => true])->assertExitCode(1);

    User::factory()->create(['email' => 'owner@demo.test'])->assignRole('owner');
    $this->artisan('clinic:create-owner', ['--check' => true])->assertExitCode(1);

    $this->artisan('clinic:create-owner', ['--name' => 'Pemilik', '--email' => 'pemilik@klinik.test', '--password' => 'Rahasia12345'])->assertSuccessful();
    $this->artisan('clinic:create-owner', ['--check' => true])->assertExitCode(0);

    expect(User::where('email', 'pemilik@klinik.test')->first()->hasRole('owner'))->toBeTrue();
});

it('memakai nama dokter sebagai judul halaman tenaga kesehatan', function () {
    $practitioner = Practitioner::factory()->create();
    $practitioner->user->update(['name' => 'Drg. Contoh Judul']);

    expect(PractitionerResource::getRecordTitle($practitioner->fresh('user')))->toBe('Drg. Contoh Judul')
        ->and(PractitionerResource::hasRecordTitle())->toBeTrue();
});
