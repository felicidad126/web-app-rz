<?php

namespace App\Filament\Widgets;

use App\Filament\Clusters\Stock\Pages\PurchaseCash;
use App\Filament\Clusters\Stock\Pages\StockOverview;
use App\Filament\Widgets\Concerns\OnStockDashboard;
use App\Services\Purchasing\Purchasing;
use App\Services\Stock\StockAlerts;
use App\Services\Stock\StockDashboard;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Empat kartu angka di atas Dashboard (Konsep UI tahap 2). Kartu kas hanya untuk yang boleh melihat kas. */
class DashboardStats extends StatsOverviewWidget
{
    use OnStockDashboard;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getColumns(): int|array|null
    {
        return ['default' => 2, '@5xl' => 4, '!@lg' => 4];
    }

    protected function getStats(): array
    {
        $branchId = $this->branchId();
        $days = (int) config('clinic.stock.expiry_warning_days', 60);

        $below = StockAlerts::belowMinimum($branchId);
        $requested = count(array_intersect_key(StockDashboard::openRequestsByItem($branchId), $below->keyBy('id')->all()));
        $expiring = StockAlerts::expiring($branchId, $days);
        $expired = $expiring->filter(fn ($batch): bool => $batch->isExpired())->count();

        $stats = [
            Stat::make('Nilai persediaan', Money::format(StockDashboard::inventoryValue($branchId)))
                ->icon(Heroicon::OutlinedCube)
                ->description(StockDashboard::itemsInStock($branchId).' barang berstok · harga pokok')
                ->color('gray')
                ->url(StockOverview::getUrl()),
            Stat::make('Di bawah minimum', $below->count().' barang')
                ->icon(Heroicon::OutlinedArrowTrendingDown)
                ->description($below->isEmpty() ? 'Aman' : 'Perlu dipesan'.($requested > 0 ? ' · '.$requested.' sudah diminta' : ''))
                ->color($below->isEmpty() ? 'gray' : 'warning')
                ->url(StockOverview::getUrl(['tampil' => 'minimum'])),
            Stat::make('Kedaluwarsa ≤ '.$days.' hari', $expiring->count().' batch')
                ->icon(Heroicon::OutlinedClock)
                ->description($expired > 0 ? $expired.' batch sudah lewat ED' : ($expiring->isEmpty() ? 'Tidak ada' : 'Pakai lebih dulu (FEFO)'))
                ->color($expired > 0 ? 'danger' : ($expiring->isEmpty() ? 'gray' : 'warning'))
                ->url(StockOverview::getUrl(['tampil' => 'kedaluwarsa'])),
        ];

        if (auth()->user()?->can('View:PurchaseCash') ?? false) {
            $stats[] = Stat::make('Saldo kas pembelian', Money::format(Purchasing::balance($branchId)))
                ->icon(Heroicon::OutlinedWallet)
                ->description('Keluar 30 hari: '.Money::format(StockDashboard::cashOut($branchId)))
                ->chart(array_map(fn (int $value): float => (float) $value, StockDashboard::cashBalanceWeekly($branchId)))
                ->chartColor('primary')
                ->color('gray')
                ->url(PurchaseCash::getUrl());
        } else {
            $negative = StockAlerts::negative($branchId)->count();
            $stats[] = Stat::make('Stok minus', $negative.' batch')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->description($negative > 0 ? 'Terpakai melebihi stok tercatat · cek saat opname' : 'Tidak ada')
                ->color($negative > 0 ? 'danger' : 'gray')
                ->url(StockOverview::getUrl(['tampil' => 'minus']));
        }

        return $stats;
    }
}
