<?php

namespace App\Filament\Clusters\Stock\Resources\PurchaseRequests\Pages;

use App\Enums\PurchaseRequestStatus;
use App\Filament\Clusters\Stock\PurchaseActions;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Clusters\Stock\StockActions;
use App\Support\BranchContext;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestResource::class;

    protected static ?string $title = 'Stok';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        return 'Permintaan pembelian · '.StockActions::branchName().'. Disetujui oleh admin/owner (bukan pengaju), lalu dipesan di tab Pesanan.';
    }

    public function getTabs(): array
    {
        $count = fn (PurchaseRequestStatus $status): ?int => ($n = PurchaseRequestResource::getEloquentQuery()
            ->where('branch_id', BranchContext::id())->where('status', $status)->count()) > 0 ? $n : null;

        return [
            'terbuka' => Tab::make('Terbuka')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', PurchaseRequestStatus::open())),
            'menunggu' => Tab::make('Menunggu persetujuan')
                ->badge(fn (): ?int => $count(PurchaseRequestStatus::Pending))->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestStatus::Pending)),
            'disetujui' => Tab::make('Siap dipesan')
                ->badge(fn (): ?int => $count(PurchaseRequestStatus::Approved))->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseRequestStatus::Approved)),
            'selesai' => Tab::make('Selesai & ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [PurchaseRequestStatus::Received, PurchaseRequestStatus::Rejected, PurchaseRequestStatus::Cancelled])),
            'semua' => Tab::make('Semua'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            StockActions::branchSwitcher(),
            PurchaseActions::requestItem(),
        ];
    }
}
