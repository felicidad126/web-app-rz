<?php

namespace App\Filament\Clusters\Stock;

use App\Filament\Support\NavGroup;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Stok (mockup "Stok"): tab Barang · Permintaan · Pesanan · Mutasi & penerimaan · Opname · Kas pembelian. */
class StockCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::OPERATIONS;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Stok';

    protected static ?string $clusterBreadcrumb = 'Stok';

    protected static ?string $slug = 'stok';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
