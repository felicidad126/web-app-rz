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
    // Fase 3: kasir & billing.
    ['kasir', '/kasir', 200],
    ['kasir', '/tagihan', 200],
    ['kasir', '/sesi-kasir', 200],
    ['kasir', '/paket-cicilan/create', 200],
    ['kasir', '/paket', 200],
    ['kasir', '/persetujuan', 403],
    ['owner', '/persetujuan', 200],
    ['owner', '/tagihan', 200],
    ['admin', '/paket', 200],
    ['admin', '/persetujuan', 403],
    ['resepsionis', '/tagihan', 200],
    ['resepsionis', '/paket-cicilan/create', 403],
    ['dokter', '/kasir', 403],
    ['dokter', '/tagihan', 403],
    ['perawat', '/sesi-kasir', 403],
    // Fase 5: stok, pembelian & lab.
    ['kasir', '/stok/barang', 200],
    ['resepsionis', '/stok/barang', 403],
    ['perawat', '/stok/mutasi', 200],
    ['perawat', '/stok/opname', 200],
    ['owner', '/stok/mutasi', 200],
    ['kasir', '/stok/mutasi', 403],
    ['dokter', '/stok/opname', 403],
    ['admin', '/supplier', 200],
    ['admin', '/kategori-barang', 200],
    ['resepsionis', '/supplier', 403],
    ['dokter', '/order-lab', 200],
    ['dokter', '/order-lab/create', 200],
    ['resepsionis', '/order-lab', 200],
    ['resepsionis', '/order-lab/create', 403],
    ['owner', '/order-lab/create', 403],
    ['kasir', '/order-lab', 403],
    // Fase 6: jasa & gaji, laporan, briefing pagi.
    ['owner', '/jasa-dokter', 200],
    ['owner', '/aturan-jasa', 200],
    ['owner', '/slip-gaji', 200],
    ['owner', '/komponen-gaji', 200],
    ['owner', '/target', 200],
    ['owner', '/laporan', 200],
    ['owner', '/briefing-pagi', 200],
    ['admin', '/jasa-dokter', 200],
    ['admin', '/kehadiran-dokter', 200],
    ['admin', '/rincian-jasa', 200],
    ['admin', '/laporan', 200],
    ['dokter', '/jasa-dokter', 200],
    ['dokter', '/rincian-jasa', 200],
    ['dokter', '/briefing-pagi', 200],
    ['dokter', '/aturan-jasa', 403],
    ['dokter', '/slip-gaji', 403],
    ['dokter', '/kehadiran-dokter', 403],
    ['dokter', '/target', 403],
    ['perawat', '/jasa-dokter', 403],
    ['perawat', '/laporan', 403],
    ['perawat', '/briefing-pagi', 200],
    ['resepsionis', '/briefing-pagi', 200],
    ['resepsionis', '/laporan', 403],
    ['kasir', '/laporan', 200],
    ['kasir', '/jasa-dokter', 403],
    ['kasir', '/briefing-pagi', 403],
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
