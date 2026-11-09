<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

it('mengarahkan tamu ke halaman login', function () {
    $this->get('/')->assertRedirect('/login');
});

it('mewajibkan pengguna tanpa MFA mengatur MFA dulu', function () {
    $user = User::factory()->create();
    $user->assignRole('owner');

    $response = $this->actingAs($user)->get('/items');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('multi-factor-authentication');
});

it('menolak pengguna nonaktif', function () {
    $user = User::factory()->withMfa()->inactive()->create();
    $user->assignRole('owner');

    $this->actingAs($user)->get('/items')->assertForbidden();
});

dataset('akses', [
    // [peran, url, status] — edisi inventori: owner, admin, perawat.
    ['owner', '/users', 200],
    ['owner', '/shield/roles', 200],
    ['owner', '/audit-log', 200],
    ['owner', '/branches', 200],
    ['admin', '/users', 200],
    ['admin', '/branches', 200],
    ['admin', '/items', 200],
    ['admin', '/supplier', 200],
    ['admin', '/kategori-barang', 200],
    ['perawat', '/audit-log', 403],
    ['perawat', '/users', 403],
    ['perawat', '/items', 200],
    ['perawat', '/items/create', 403],
    ['perawat', '/supplier', 200],
    // Stok & pembelian: perawat meminta & menerima, admin memesan, owner/admin pegang kas.
    ['perawat', '/stok/barang', 200],
    ['perawat', '/stok/mutasi', 200],
    ['perawat', '/stok/opname', 200],
    ['perawat', '/stok/permintaan', 200],
    ['perawat', '/stok/pesanan', 200],
    ['perawat', '/stok/pesanan/buat', 403],
    ['perawat', '/stok/kas-pembelian', 403],
    ['admin', '/stok/pesanan/buat', 200],
    ['admin', '/stok/kas-pembelian', 200],
    ['owner', '/stok/mutasi', 200],
    ['owner', '/stok/permintaan', 200],
    ['owner', '/stok/pesanan/buat', 403],
    ['owner', '/stok/kas-pembelian', 200],
    // Laporan stok & kas pembelian: owner/admin.
    ['owner', '/laporan', 200],
    ['admin', '/laporan', 200],
    ['perawat', '/laporan', 403],
    ['perawat', '/panduan', 200],
    // Modul klinik tidak ada di edisi inventori.
    ['owner', '/patients', 404],
    ['owner', '/jadwal', 404],
    ['owner', '/kunjungan', 404],
    ['owner', '/kasir', 404],
    ['owner', '/tagihan', 404],
    ['owner', '/jasa-dokter', 404],
    ['owner', '/briefing-pagi', 404],
    ['owner', '/satusehat', 404],
    ['owner', '/kesiapan-cabang', 404],
    ['owner', '/order-lab', 404],
    ['owner', '/treatments', 404],
]);

it('membatasi halaman sesuai matriks akses', function (string $role, string $url, int $status) {
    loginAs($role);

    $this->get($url)->assertStatus($status);
})->with('akses');

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
