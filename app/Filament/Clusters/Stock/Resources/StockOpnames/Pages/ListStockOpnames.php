<?php

namespace App\Filament\Clusters\Stock\Resources\StockOpnames\Pages;

use App\Filament\Clusters\Stock\Resources\StockOpnames\StockOpnameResource;
use App\Filament\Clusters\Stock\StockActions;
use Filament\Resources\Pages\ListRecords;

class ListStockOpnames extends ListRecords
{
    protected static string $resource = StockOpnameResource::class;

    protected static ?string $title = 'Stok';

    /** Sama dengan tab Barang: judul "Stok" + tab sudah menunjukkan posisi. */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected ?string $subheading = 'Opname pertama di tiap cabang menjadi saldo awal stok. Selisih hitung tercatat setelah disetujui admin klinik.';

    protected function getHeaderActions(): array
    {
        // Di tab Opname, memulai opname adalah aksi utama.
        return [StockActions::branchSwitcher(), StockActions::startOpname()->color('primary')];
    }
}
