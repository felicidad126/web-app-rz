<?php

namespace App\Filament\Clusters\Stock;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Stok (mockup "Stok"): tab Barang · Permintaan · Pesanan · Mutasi & penerimaan · Opname · Kas pembelian. */
class StockCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    // Edisi inventori: Stok di atas menu tanpa judul kelompok (Konsep UI, 10 Nov 2026).
    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Stok';

    protected static ?string $clusterBreadcrumb = 'Stok';

    protected static ?string $slug = 'stok';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
