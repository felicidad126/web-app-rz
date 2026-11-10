<?php

namespace App\Filament\Pages;

use App\Enums\PurchaseRequestStatus;
use App\Filament\Clusters\Stock\PurchaseActions;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
use App\Filament\Clusters\Stock\Resources\StockOpnames\StockOpnameResource;
use App\Filament\Clusters\Stock\StockActions;
use App\Filament\Widgets\ActionItems;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\PurchasingFlow;
use App\Filament\Widgets\StockFlowChart;
use App\Filament\Widgets\StockValueByCategory;
use App\Models\PurchaseRequest;
use App\Services\Stock\StockDashboard;
use App\Support\BranchContext;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Dashboard edisi inventori (Konsep UI tahap 2): kartu angka, grafik pemakaian & penerimaan, nilai per kategori,
 * alur pembelian, dan daftar perlu tindakan. Satu tombol utama sesuai peran.
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
        [$approvals, $approvalsUrl] = $this->approvals();

        return [
            StockActions::branchSwitcher(),
            Action::make('approvals')
                ->label('Tinjau persetujuan · '.$approvals)
                ->icon(Heroicon::OutlinedCheckBadge)
                ->url($approvalsUrl)
                ->visible($approvals > 0),
            PurchaseActions::requestItem()
                ->label('Minta beli')
                ->visible(fn (): bool => $approvals === 0 && BranchContext::id() !== null
                    && (auth()->user()?->can('Create:PurchaseRequest') ?? false)),
        ];
    }

    /**
     * Jumlah hal yang menunggu persetujuan pengguna ini dan halaman pertama untuk meninjaunya.
     *
     * @return array{0: int, 1: ?string}
     */
    private function approvals(): array
    {
        $branchId = BranchContext::id();
        $user = auth()->user();

        if ($branchId === null || $user === null) {
            return [0, null];
        }

        $requests = $user->can('Approve:Purchase')
            ? PurchaseRequest::query()->where('branch_id', $branchId)->where('status', PurchaseRequestStatus::Pending)->count()
            : 0;
        $stock = $user->can('Approve:Stock') ? StockDashboard::pendingStockApprovals($branchId) : ['disposals' => 0, 'opnames' => 0];

        $url = match (true) {
            $requests > 0 => PurchaseRequestResource::getUrl('index', ['tab' => 'menunggu']),
            $stock['disposals'] > 0 => StockMovementResource::getUrl('index', ['tab' => 'persetujuan']),
            $stock['opnames'] > 0 => StockOpnameResource::getUrl('index'),
            default => null,
        };

        return [$requests + $stock['disposals'] + $stock['opnames'], $url];
    }
}
