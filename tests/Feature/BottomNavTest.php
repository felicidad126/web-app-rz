<?php

use App\Enums\ItemType;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Filament\Support\BottomNav;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Unit;
use App\Models\User;
use App\Services\Purchasing\Purchasing;
use Livewire\Livewire;

/** Menu bawah di HP (Konsep UI tahap 3): isi sesuai izin, tanda halaman aktif, tombol Minta beli membuka form. */
beforeEach(function () {
    $this->branch = Branch::factory()->create(['code' => 'KMG']);
    $this->pcs = Unit::create(['name' => 'pcs']);
    $this->glove = Item::create(['sku' => 'GLV-M', 'name' => 'Sarung tangan M', 'type' => ItemType::Material, 'base_unit_id' => $this->pcs->id, 'min_stock' => 100]);

    foreach (['perawat', 'admin', 'owner'] as $role) {
        $this->{$role} = User::factory()->withMfa()->create();
        $this->{$role}->assignRole($role);
        $this->{$role}->branches()->sync([$this->branch->id]);
    }
});

/** Label tombol yang ditandai aktif di menu bawah pada halaman ini. */
function activeBottomNavLabel(string $html): ?string
{
    preg_match('#<nav class="klinik-bottom-nav".*?</nav>#s', $html, $nav);
    preg_match('#klinik-bnav-active"[^>]*>.*?klinik-bnav-label">([^<]+)<#s', $nav[0] ?? '', $label);

    return isset($label[1]) ? trim($label[1]) : null;
}

it('isi menu bawah mengikuti izin tiap peran', function () {
    $this->actingAs($this->perawat);
    $perawat = BottomNav::items();
    expect(array_column($perawat, 'label'))->toBe(['Dashboard', 'Stok', 'Minta beli', 'Permintaan', 'Menu'])
        ->and($perawat[2])->primary->toBeTrue()->url->toContain('action=createRequest');

    $this->actingAs($this->admin);
    expect(array_column(BottomNav::items(), 'label'))->toBe(['Dashboard', 'Stok', 'Minta beli', 'Laporan', 'Menu']);

    // Owner hanya menyetujui (tidak membuat permintaan): tombol tengah = Persetujuan + jumlah yang menunggu.
    $this->actingAs($this->owner);
    $owner = BottomNav::items();
    expect(array_column($owner, 'label'))->toBe(['Dashboard', 'Stok', 'Persetujuan', 'Laporan', 'Menu'])
        ->and($owner[2])->primary->toBeTrue()->badge->toBeNull();

    Purchasing::requestItem($this->branch->id, $this->glove->id, 2, $this->pcs->id, 'Stok menipis', $this->perawat);
    expect(BottomNav::items()[2])->badge->toBe(1)->url->toContain('tab=menunggu');
});

it('menandai halaman aktif; halaman tanpa tombol sendiri menandai Menu', function () {
    $this->actingAs($this->perawat);

    expect(activeBottomNavLabel($this->get('/')->assertOk()->getContent()))->toBe('Dashboard')
        ->and(activeBottomNavLabel($this->get('/stok/barang')->assertOk()->getContent()))->toBe('Stok')
        ->and(activeBottomNavLabel($this->get('/stok/permintaan')->assertOk()->getContent()))->toBe('Permintaan')
        ->and(activeBottomNavLabel($this->get('/items')->assertOk()->getContent()))->toBe('Menu');

    $this->actingAs($this->admin);
    expect(activeBottomNavLabel($this->get('/stok/permintaan')->assertOk()->getContent()))->toBe('Stok')
        ->and(activeBottomNavLabel($this->get('/laporan')->assertOk()->getContent()))->toBe('Laporan');
});

it('tombol Minta beli membuka form permintaan di halaman Permintaan', function () {
    $this->actingAs($this->perawat);

    // Di browser form dibuka lewat wire:init="mountDefaultActions"; di tes dipanggil langsung.
    Livewire::withQueryParams(['action' => 'createRequest'])->test(ListPurchaseRequests::class)
        ->call('mountDefaultActions')
        ->assertSet('mountedActions.0.name', 'createRequest')
        ->assertMountedActionModalSee('Minta pembelian');
});

it('halaman login tidak memuat menu bawah', function () {
    $this->get('/login')->assertOk()->assertDontSee('klinik-bottom-nav', false);
});
