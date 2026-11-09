<?php

namespace App\Filament\Widgets;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\StockMovementStatus;
use App\Enums\StockOpnameStatus;
use App\Filament\Clusters\Stock\Pages\StockOverview;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
use App\Filament\Clusters\Stock\Resources\StockOpnames\StockOpnameResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Services\Stock\StockAlerts;
use App\Support\BranchContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Peringatan stok & pembelian di dashboard (edisi inventori). */
class StockAlertsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Stok';

    /** Dua kartu per baris di HP; di layar lebar semua kartu (3–5) dalam satu baris. */
    protected function getColumns(): int|array|null
    {
        $count = count($this->getCachedStats());

        return ['default' => 2, '@3xl' => min(3, $count), '@5xl' => $count, '!@lg' => $count];
    }

    public static function canView(): bool
    {
        return (auth()->user()?->can('View:Stock') ?? false) && BranchContext::id() !== null;
    }

    protected function getDescription(): ?string
    {
        return BranchContext::available()->firstWhere('id', BranchContext::id())?->name;
    }

    protected function getStats(): array
    {
        $branchId = (int) BranchContext::id();
        $days = (int) config('clinic.stock.expiry_warning_days', 60);
        $below = StockAlerts::belowMinimum($branchId)->count();
        $expiring = StockAlerts::expiring($branchId);
        $expired = $expiring->filter(fn ($batch): bool => $batch->isExpired())->count();
        $negative = StockAlerts::negative($branchId)->count();
        $pending = StockMovement::query()->where('branch_id', $branchId)->where('status', StockMovementStatus::Pending)->count()
            + StockOpname::query()->where('branch_id', $branchId)->where('status', StockOpnameStatus::Pending)->count();

        $stats = [
            Stat::make('Di bawah minimum', $below)
                ->description($below > 0 ? 'Perlu dipesan' : 'Aman')
                ->color($below > 0 ? 'warning' : 'gray')
                ->url(StockOverview::getUrl(['tampil' => 'minimum'])),
            Stat::make('Kedaluwarsa ≤ '.$days.' hari', $expiring->count())
                ->description($expired > 0 ? $expired.' batch sudah lewat ED' : 'Batch berstok')
                ->color($expired > 0 ? 'danger' : ($expiring->isNotEmpty() ? 'warning' : 'gray'))
                ->url(StockOverview::getUrl(['tampil' => 'kedaluwarsa'])),
            Stat::make('Stok minus', $negative)
                ->description($negative > 0 ? 'Terpakai melebihi stok tercatat · cek saat opname' : 'Tidak ada')
                ->color($negative > 0 ? 'danger' : 'gray')
                ->url(StockOverview::getUrl(['tampil' => 'minus'])),
        ];

        if ($pending > 0 || (auth()->user()?->can('Approve:Stock') ?? false)) {
            $stats[] = Stat::make('Menunggu persetujuan', $pending)
                ->description('Pemusnahan & opname')
                ->color($pending > 0 ? 'warning' : 'gray')
                ->url($pending > 0 && StockOpname::query()->where('branch_id', $branchId)->where('status', StockOpnameStatus::Pending)->exists()
                    ? StockOpnameResource::getUrl('index')
                    : StockMovementResource::getUrl('index', ['tab' => 'persetujuan']));
        }

        if (auth()->user()?->can('ViewAny:PurchaseRequest') ?? false) {
            $stats[] = $this->purchasingStat($branchId);
        }

        return $stats;
    }

    /** Pembelian: permintaan menunggu persetujuan & pesanan yang belum datang (kuning bila perlu tindakan). */
    private function purchasingStat(int $branchId): Stat
    {
        $canApprove = auth()->user()?->can('Approve:Purchase') ?? false;
        $pending = PurchaseRequest::query()->where('branch_id', $branchId)->where('status', PurchaseRequestStatus::Pending)->count();
        $open = PurchaseOrder::query()->where('branch_id', $branchId)->whereIn('status', [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Partial]);
        $waiting = (clone $open)->count();
        $late = (clone $open)->whereDate('expected_at', '<', today())->count();
        $needsAction = ($canApprove && $pending > 0) || $late > 0;

        return Stat::make('Pembelian', $waiting.' pesanan belum datang')
            ->description(collect([
                $pending > 0 ? $pending.' permintaan menunggu persetujuan' : null,
                $late > 0 ? $late.' lewat perkiraan datang' : null,
            ])->filter()->implode(' · ') ?: 'Tidak ada yang menunggu')
            ->color($needsAction ? 'warning' : 'gray')
            ->url($canApprove && $pending > 0
                ? PurchaseRequestResource::getUrl('index', ['tab' => 'menunggu'])
                : (PurchaseOrderResource::canViewAny() ? PurchaseOrderResource::getUrl('index') : PurchaseRequestResource::getUrl('index')));
    }
}
