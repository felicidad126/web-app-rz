<?php

use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;

it('mengisi data demo tanpa factory/Faker dan memakai password dari config', function () {
    config(['clinic.demo_password' => 'demo-rahasia-123']);

    $this->seed(DemoSeeder::class);

    expect(Patient::count())->toBe(25)
        ->and(Patient::whereNull('nik_hash')->count())->toBe(0)
        ->and(Hash::check('demo-rahasia-123', User::where('email', 'owner@demo.test')->value('password')))->toBeTrue()
        ->and(User::where('email', 'kasir@demo.test')->first()->hasRole('kasir'))->toBeTrue();
});

it('menolak berjalan di produksi', function () {
    app()->detectEnvironment(fn () => 'production');

    // Panggil langsung (db:seed di produksi meminta konfirmasi interaktif).
    app(DemoSeeder::class)->run();
})->throws(RuntimeException::class);
