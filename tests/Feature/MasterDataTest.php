<?php

use App\Models\Icd10Code;
use App\Models\Item;
use App\Models\OdontogramCondition;
use App\Models\Payer;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;

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

it('clinic:create-owner --check membedakan owner asli dan akun demo', function () {
    $this->artisan('clinic:create-owner', ['--check' => true])->assertExitCode(1);

    User::factory()->create(['email' => 'owner@demo.test'])->assignRole('owner');
    $this->artisan('clinic:create-owner', ['--check' => true])->assertExitCode(1);

    $this->artisan('clinic:create-owner', ['--name' => 'Pemilik', '--email' => 'pemilik@klinik.test', '--password' => 'Rahasia12345'])->assertSuccessful();
    $this->artisan('clinic:create-owner', ['--check' => true])->assertExitCode(0);

    expect(User::where('email', 'pemilik@klinik.test')->first()->hasRole('owner'))->toBeTrue();
});
