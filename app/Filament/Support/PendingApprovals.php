<?php

namespace App\Filament\Support;

use App\Enums\PurchaseRequestStatus;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
use App\Filament\Clusters\Stock\Resources\StockOpnames\StockOpnameResource;
use App\Models\PurchaseRequest;
use App\Services\Stock\StockDashboard;
use App\Support\BranchContext;

/**
 * Hal yang menunggu persetujuan pengguna yang login di cabang aktif (permintaan beli, pemusnahan, opname),
 * dipakai tombol "Tinjau persetujuan" di Dashboard dan tombol Persetujuan di menu bawah HP.
 */
final class PendingApprovals
{
    /**
     * Jumlah yang menunggu dan halaman pertama untuk meninjaunya (null bila tidak ada).
     *
     * @return array{count: int, url: ?string}
     */
    public static function forCurrentUser(): array
    {
        $branchId = BranchContext::id();
        $user = auth()->user();

        if ($branchId === null || $user === null) {
            return ['count' => 0, 'url' => null];
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

        return ['count' => $requests + $stock['disposals'] + $stock['opnames'], 'url' => $url];
    }
}
