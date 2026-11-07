<?php

namespace App\Filament\Clusters\Stock\Resources\PurchaseOrders\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Clusters\Stock\StockActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static ?string $title = 'Stok';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        return 'Pesanan ke supplier · '.StockActions::branchName().'. Stok bertambah saat barang diterima, bukan saat dipesan.';
    }

    public function getTabs(): array
    {
        return [
            'belum' => Tab::make('Belum datang')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Partial])),
            'diterima' => Tab::make('Diterima')->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Received)),
            'batal' => Tab::make('Dibatalkan')->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Cancelled)),
            'semua' => Tab::make('Semua'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            StockActions::branchSwitcher(),
            CreateAction::make()->label('Buat pesanan'),
        ];
    }
}
