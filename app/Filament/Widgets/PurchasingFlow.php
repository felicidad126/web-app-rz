<?php

namespace App\Filament\Widgets;

use App\Filament\Clusters\Stock\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Widgets\Concerns\OnStockDashboard;
use App\Services\Purchasing\Purchasing;
use App\Services\Stock\StockDashboard;
use App\Services\Stock\StockLedger;
use App\Support\BranchContext;
use App\Support\Money;
use Filament\Widgets\Widget;

/** Panel gelap "Alur pembelian": permintaan & pesanan yang masih terbuka di cabang aktif. */
class PurchasingFlow extends Widget
{
    use OnStockDashboard;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 1, 'xl' => 7];

    protected string $view = 'filament.widgets.purchasing-flow';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('ViewAny:PurchaseRequest') && $user->can('View:Stock') && BranchContext::id() !== null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $branchId = $this->branchId();
        $data = StockDashboard::purchasing($branchId);
        $request = fn ($r): ?string => $r ? $r->item?->name.' · '.StockLedger::format((float) $r->quantity).' '.$r->unit?->name : null;
        $order = $data['openExample'];
        $canSeeOrders = PurchaseOrderResource::canViewAny();
        $canSeeCash = auth()->user()?->can('View:PurchaseCash') ?? false;

        return [
            'total' => $data['pending'] + $data['approved'] + $data['open'],
            'tiles' => [
                [
                    'label' => 'Menunggu persetujuan', 'count' => $data['pending'], 'dot' => 'warning',
                    'text' => $request($data['pendingExample']) ?? 'Tidak ada', 'code' => $data['pendingExample']?->number,
                    'url' => PurchaseRequestResource::getUrl('index', ['tab' => 'menunggu']),
                ],
                [
                    'label' => 'Siap dipesan', 'count' => $data['approved'], 'dot' => 'primary',
                    'text' => $request($data['approvedExample']) ?? 'Tidak ada', 'code' => $data['approvedExample']?->number,
                    'url' => PurchaseRequestResource::getUrl('index', ['tab' => 'disetujui']),
                ],
                [
                    'label' => 'Belum datang lengkap', 'count' => $data['open'], 'dot' => $data['late'] > 0 ? 'warning' : 'primary',
                    'text' => $data['late'] > 0 ? $data['late'].' lewat perkiraan datang' : ($order?->supplier->name ?? 'Tidak ada'),
                    'code' => $order?->number,
                    'url' => $canSeeOrders ? PurchaseOrderResource::getUrl('index', ['tab' => 'belum']) : PurchaseRequestResource::getUrl('index'),
                ],
            ],
            'cash' => $canSeeCash ? [
                'balance' => Money::format(Purchasing::balance($branchId)),
                'out' => Money::format(StockDashboard::cashOut($branchId)),
            ] : null,
            'requestsUrl' => PurchaseRequestResource::getUrl('index'),
        ];
    }
}
