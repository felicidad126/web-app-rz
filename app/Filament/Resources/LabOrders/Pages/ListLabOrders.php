<?php

namespace App\Filament\Resources\LabOrders\Pages;

use App\Enums\LabOrderStatus;
use App\Filament\Resources\LabOrders\LabOrderResource;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLabOrders extends ListRecords
{
    protected static string $resource = LabOrderResource::class;

    public function getSubheading(): ?string
    {
        $query = LabOrderResource::getEloquentQuery();
        $active = (clone $query)->whereIn('status', LabOrderStatus::active())->count();
        $late = (clone $query)->whereIn('status', [LabOrderStatus::Created, LabOrderStatus::Sent])->whereDate('due_at', '<', today())->count();
        $cost = (int) (clone $query)->where('status', '!=', LabOrderStatus::Cancelled)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('cost');

        return $active.' order aktif'.($late > 0 ? ' · '.$late.' terlambat' : '').' · biaya lab bulan ini '.Money::format($cost);
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('lab_orders.status', LabOrderStatus::active())),
            'fitted' => Tab::make('Sudah dipasang')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('lab_orders.status', LabOrderStatus::Fitted)),
            'cancelled' => Tab::make('Batal')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('lab_orders.status', LabOrderStatus::Cancelled)),
            'all' => Tab::make('Semua'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat order lab'),
        ];
    }
}
