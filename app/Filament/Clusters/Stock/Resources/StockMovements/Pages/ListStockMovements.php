<?php

namespace App\Filament\Clusters\Stock\Resources\StockMovements\Pages;

use App\Enums\StockMovementStatus;
use App\Enums\StockMovementType;
use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
use App\Filament\Clusters\Stock\StockActions;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected static ?string $title = 'Stok';

    /** Sama dengan tab Barang: judul "Stok" + tab sudah menunjukkan posisi. */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'penerimaan' => Tab::make('Penerimaan')->modifyQueryUsing(fn (Builder $query) => $query->where('type', StockMovementType::Receipt)),
            'keluar' => Tab::make('Pemakaian & penjualan')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', [StockMovementType::Usage, StockMovementType::Dispensing, StockMovementType::Sale])),
            'transfer' => Tab::make('Transfer')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', [StockMovementType::TransferIn, StockMovementType::TransferOut])),
            'koreksi' => Tab::make('Pemusnahan & koreksi')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', [StockMovementType::Disposal, StockMovementType::Adjustment, StockMovementType::Reversal])),
            'persetujuan' => Tab::make('Menunggu persetujuan')
                ->badge(fn (): ?int => ($n = StockMovementResource::getEloquentQuery()->where('status', StockMovementStatus::Pending)->count()) > 0 ? $n : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StockMovementStatus::Pending)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            StockActions::branchSwitcher(),
            ActionGroup::make([StockActions::usage(), StockActions::transfer(), StockActions::disposal()])
                ->label('Lainnya')->button()->color('gray'),
            StockActions::receive(),
        ];
    }
}
