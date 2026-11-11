<?php

namespace App\Filament\Support;

use App\Filament\Clusters\Stock\Pages\StockOverview;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Clusters\Stock\StockCluster;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports;
use App\Support\BranchContext;
use Filament\Support\Icons\Heroicon;

/**
 * Menu bawah di HP & tablet tegak (Konsep UI tahap 3, resources/views/filament/bottom-nav.blade.php).
 * Isinya mengikuti izin, bukan nama peran:
 * Dashboard · Stok · [tengah: Minta beli, atau Persetujuan bagi yang hanya menyetujui] · Laporan/Permintaan · Menu.
 */
final class BottomNav
{
    /**
     * @return list<array{key: string, label: string, icon: Heroicon, url: ?string, active: bool, primary: bool, badge: ?int}>
     */
    public static function items(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $items = [self::item('dashboard', 'Dashboard', Heroicon::OutlinedHome, Dashboard::getUrl())];

        if (StockOverview::canAccess()) {
            $items[] = self::item('stock', 'Stok', Heroicon::OutlinedArchiveBox, StockOverview::getUrl());
        }

        if ($user->can('Create:PurchaseRequest') && BranchContext::id() !== null) {
            $items[] = self::item('request', 'Minta beli', Heroicon::OutlinedPlus,
                PurchaseRequestResource::getUrl('index', ['action' => 'createRequest']), primary: true);
        } elseif ($user->can('Approve:Purchase') || $user->can('Approve:Stock')) {
            $pending = PendingApprovals::forCurrentUser();
            $items[] = self::item('approvals', 'Persetujuan', Heroicon::OutlinedCheckBadge,
                $pending['url'] ?? PurchaseRequestResource::getUrl('index', ['tab' => 'menunggu']),
                primary: true, badge: $pending['count'] > 0 ? $pending['count'] : null);
        }

        if (Reports::canAccess()) {
            $items[] = self::item('reports', 'Laporan', Heroicon::OutlinedChartBar, Reports::getUrl());
        } elseif (PurchaseRequestResource::canViewAny()) {
            $items[] = self::item('requests', 'Permintaan', Heroicon::OutlinedShoppingCart, PurchaseRequestResource::getUrl('index'));
        }

        $items[] = self::item('menu', 'Menu', Heroicon::OutlinedBars3, null);

        return self::markActive($items);
    }

    /**
     * @return array{key: string, label: string, icon: Heroicon, url: ?string, active: bool, primary: bool, badge: ?int}
     */
    private static function item(string $key, string $label, Heroicon $icon, ?string $url, bool $primary = false, ?int $badge = null): array
    {
        return ['key' => $key, 'label' => $label, 'icon' => $icon, 'url' => $url, 'active' => false, 'primary' => $primary, 'badge' => $badge];
    }

    /**
     * Tanda halaman aktif: bagian yang sedang dibuka; halaman tanpa tombol sendiri (master barang, pengguna, panduan…)
     * menandai Menu. Tombol tengah adalah aksi, tidak pernah ditandai.
     *
     * @param  list<array{key: string, label: string, icon: Heroicon, url: ?string, active: bool, primary: bool, badge: ?int}>  $items
     * @return list<array{key: string, label: string, icon: Heroicon, url: ?string, active: bool, primary: bool, badge: ?int}>
     */
    private static function markActive(array $items): array
    {
        $keys = array_column($items, 'key');
        $request = request();

        $current = match (true) {
            $request->routeIs(Dashboard::getRouteName()) => 'dashboard',
            $request->routeIs(Reports::getRouteName()) => 'reports',
            in_array('requests', $keys, true) && $request->routeIs(PurchaseRequestResource::getRouteBaseName().'.*') => 'requests',
            $request->routeIs(StockCluster::getRouteName(), StockCluster::getRouteName().'.*') => 'stock',
            default => 'menu',
        };

        if (! in_array($current, $keys, true)) {
            $current = 'menu';
        }

        return array_map(fn (array $item): array => [...$item, 'active' => $item['key'] === $current], $items);
    }
}
