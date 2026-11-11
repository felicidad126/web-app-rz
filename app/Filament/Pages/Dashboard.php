<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Stock\PurchaseActions;
use App\Filament\Clusters\Stock\StockActions;
use App\Filament\Support\PendingApprovals;
use App\Filament\Widgets\ActionItems;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\PurchasingFlow;
use App\Filament\Widgets\StockFlowChart;
use App\Filament\Widgets\StockValueByCategory;
use App\Support\BranchContext;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Dashboard edisi inventori (Konsep UI tahap 2): kartu angka, grafik pemakaian & penerimaan, nilai per kategori,
 * alur pembelian, dan daftar perlu tindakan. Satu tombol utama sesuai peran (di HP: tombol tengah menu bawah).
 */
class Dashboard extends BaseDashboard
{
    public function getSubheading(): ?string
    {
        $branch = BranchContext::available()->firstWhere('id', BranchContext::id())?->name;

        return collect([
            'Selamat datang, '.auth()->user()?->name,
            $branch,
            now()->locale('id')->translatedFormat('l, j F Y'),
        ])->filter()->implode(' · ');
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 12];
    }

    public function getWidgets(): array
    {
        return [DashboardStats::class, StockFlowChart::class, StockValueByCategory::class, PurchasingFlow::class, ActionItems::class];
    }

    protected function getHeaderActions(): array
    {
        $pending = PendingApprovals::forCurrentUser();

        return [
            StockActions::branchSwitcher(),
            Action::make('approvals')
                ->label('Tinjau persetujuan · '.$pending['count'])
                ->icon(Heroicon::OutlinedCheckBadge)
                ->url($pending['url'])
                ->visible($pending['count'] > 0),
            // Di HP tombol ini sama dengan tombol tengah menu bawah, jadi disembunyikan di sana.
            PurchaseActions::requestItem()
                ->label('Minta beli')
                ->extraAttributes(['class' => 'klinik-hide-below-lg'])
                ->visible(fn (): bool => $pending['count'] === 0 && BranchContext::id() !== null
                    && (auth()->user()?->can('Create:PurchaseRequest') ?? false)),
        ];
    }
}
