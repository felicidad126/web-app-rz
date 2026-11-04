<?php

use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

it('mengarahkan tamu ke halaman login', function () {
    $this->get('/')->assertRedirect('/login');
});

it('mewajibkan pengguna tanpa MFA mengatur MFA dulu', function () {
    $user = User::factory()->create();
    $user->assignRole('owner');

    $response = $this->actingAs($user)->get('/patients');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('multi-factor-authentication');
});

it('menolak pengguna nonaktif', function () {
    $user = User::factory()->withMfa()->inactive()->create();
    $user->assignRole('owner');

    $this->actingAs($user)->get('/patients')->assertForbidden();
});

dataset('akses', [
    // [peran, url, status]
    ['owner', '/users', 200],
    ['owner', '/shield/roles', 200],
    ['owner', '/audit-log', 200],
    ['admin', '/users', 200],
    ['admin', '/branches', 200],
    ['dokter', '/patients', 200],
    ['dokter', '/treatments', 200],
    ['dokter', '/users', 403],
    ['dokter', '/branches', 403],
    ['dokter', '/patients/create', 403],
    ['perawat', '/audit-log', 403],
    ['resepsionis', '/patients/create', 200],
    ['resepsionis', '/items', 403],
    ['resepsionis', '/shield/roles', 403],
    ['kasir', '/patients', 200],
    ['kasir', '/patients/create', 403],
    ['kasir', '/treatments/create', 403],
    ['kasir', '/users', 403],
]);

it('membatasi halaman sesuai matriks akses', function (string $role, string $url, int $status) {
    loginAs($role);

    $this->get($url)->assertStatus($status);
})->with('akses');

it('hanya owner & admin yang boleh menghapus pasien', function () {
    $patient = Patient::factory()->create();

    foreach (['owner' => true, 'admin' => true, 'dokter' => false, 'perawat' => false, 'resepsionis' => false, 'kasir' => false] as $role => $allowed) {
        $user = User::factory()->withMfa()->create();
        $user->assignRole($role);

        expect($user->can('delete', $patient))->toBe($allowed, "peran {$role}");
    }
});

it('tidak memberi siapa pun izin hapus permanen', function () {
    foreach (RolePermissionSeeder::matrix() as $role => $permissions) {
        expect(collect($permissions)->filter(fn (string $p) => str_starts_with($p, 'ForceDelete')))->toBeEmpty("peran {$role}");
    }
});

it('memberi super_admin akses penuh lewat gate', function () {
    $user = User::factory()->withMfa()->create();
    $user->assignRole(config('filament-shield.super_admin.name'));

    $this->actingAs($user)->get('/users')->assertOk();
});
