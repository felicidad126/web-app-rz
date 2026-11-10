<?php

namespace App\Filament\Widgets;

use App\Filament\Clusters\Stock\Pages\StockOverview;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
use App\Filament\Clusters\Stock\Resources\StockOpnames\StockOpnameResource;
use App\Filament\Widgets\Concerns\OnStockDashboard;
use App\Models\User;
use App\Services\Stock\StockDashboard;
use Filament\Widgets\Widget;

/** Daftar "Perlu tindakan": paling mendesak dulu, tiap baris menuju tempat tindakannya. */
class ActionItems extends Widget
{
    use OnStockDashboard;

    public const LIMIT = 6;

    private const ICONS = [
        'expired' => 'heroicon-o-clock', 'expiring' => 'heroicon-o-clock', 'negative' => 'heroicon-o-exclamation-triangle',
        'approve-disposal' => 'heroicon-o-check-badge', 'approve-opname' => 'heroicon-o-check-badge', 'approve-request' => 'heroicon-o-check-badge',
        'late-order' => 'heroicon-o-truck', 'incoming-order' => 'heroicon-o-truck', 'below-minimum' => 'heroicon-o-arrow-trending-down',
    ];

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['default' => 1, 'xl' => 5];

    protected string $view = 'filament.widgets.action-items';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $all = StockDashboard::actionItems($this->branchId(), $user);
        $urgent = $all->where('tone', '!=', 'gray')->count();

        return [
            'items' => $all->take(self::LIMIT)->map(fn (array $item): array => [
                ...$item,
                'icon' => self::ICONS[$item['kind']],
                'url' => $this->url($item),
            ])->all(),
            'urgent' => $urgent,
            'more' => max(0, $all->count() - self::LIMIT),
            'moreUrl' => StockOverview::getUrl(),
        ];
    }

    /** @param array{kind: string, id: ?int} $item */
    private function url(array $item): string
    {
        return match ($item['kind']) {
            'expired', 'expiring' => StockOverview::getUrl(['tampil' => 'kedaluwarsa']),
            'negative' => StockOverview::getUrl(['tampil' => 'minus']),
            'approve-disposal' => StockMovementResource::getUrl('index', ['tab' => 'persetujuan']),
            'approve-opname' => StockOpnameResource::getUrl('index'),
            'approve-request' => PurchaseRequestResource::getUrl('index', ['tab' => 'menunggu']),
            'late-order', 'incoming-order' => PurchaseOrderResource::canViewAny() && $item['id'] !== null
                ? PurchaseOrderResource::getUrl('view', ['record' => $item['id']])
                : PurchaseRequestResource::getUrl('index'),
            'below-minimum' => $item['id'] !== null
                ? PurchaseRequestResource::getUrl('index', ['tab' => 'terbuka'])
                : StockOverview::getUrl(['tampil' => 'minimum']),
            default => StockOverview::getUrl(),
        };
    }
}
