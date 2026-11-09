<?php

use App\Filament\Pages\Guide;
use App\Support\GuideContent;
use Livewire\Livewire;

it('panduan menampilkan bagian umum + peran pengguna; owner melihat semua', function () {
    $perawat = loginAs('perawat');
    expect(array_keys(Guide::sectionsFor($perawat)))->toBe(['umum', 'perawat']);

    // Halaman utama: judul + cari, kartu peran yang boleh dilihat (peran sendiri ditandai), pertanyaan umum.
    Livewire::test(Guide::class)->assertOk()
        ->assertSee('Butuh bantuan?')->assertSee('Peran Anda')->assertSee('Lihat panduan')
        ->assertSee('Lupa password, bagaimana?')
        ->assertSee('Barang pesanan datang') // ada di indeks pencarian
        ->assertDontSee('Admin klinik</span>', false)
        ->assertDontSee('Tutup sisa pesanan');

    // Halaman peran: daftar topik di kiri, isi di kanan.
    Livewire::withQueryParams(['peran' => 'perawat'])->test(Guide::class)->assertOk()
        ->assertSee('Semua panduan')->assertSee('Barang masuk')->assertSee('Terima barang')
        ->assertSee('href="#opname"', false);

    // Peran lain tidak bisa dibuka lewat URL: kembali ke halaman utama.
    Livewire::withQueryParams(['peran' => 'admin'])->test(Guide::class)->assertOk()
        ->assertSee('Butuh bantuan?')->assertDontSee('Tutup sisa pesanan');

    $owner = loginAs('owner');
    expect(array_keys(Guide::sectionsFor($owner)))->toBe(['umum', 'perawat', 'admin', 'owner']);

    foreach (array_keys(Guide::ROLES) as $role) {
        expect(resource_path("guides/{$role}.md"))->toBeFile()
            ->and(GuideContent::topics($role, Guide::ROLES[$role]))->not->toBeEmpty()
            ->and(Guide::DESCRIPTIONS[$role] ?? null)->not->toBeNull();
    }

    expect(collect(GuideContent::topics('perawat', 'Perawat'))->pluck('title')->all())
        ->toBe(['Barang masuk', 'Pemakaian', 'Kedaluwarsa & rusak', 'Opname', 'Minta beli', 'Barang pesanan datang', 'Riwayat stok'])
        ->and(GuideContent::topics('faq'))->toHaveCount(7);
});
