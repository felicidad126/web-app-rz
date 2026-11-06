<?php

namespace App\Filament\Widgets;

use App\Enums\LabOrderStatus;
use App\Enums\StockMovementStatus;
use App\Enums\StockOpnameStatus;
use App\Filament\Clusters\Stock\Pages\StockOverview;
use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
use App\Filament\Clusters\Stock\Resources\StockOpnames\StockOpnameResource;
use App\Filament\Resources\LabOrders\LabOrderResource;
use App\Models\LabOrder;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Services\Stock\StockAlerts;
use App\Support\BranchContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Peringatan stok & order lab di dashboard (Planning §5.8: widget admin/perawat). */
class StockAlertsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Stok & lab';

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

        if (auth()->user()?->can('ViewAny:LabOrder') ?? false) {
            $lab = LabOrder::query()->where('branch_id', $branchId)->whereIn('status', [LabOrderStatus::Created, LabOrderStatus::Sent]);
            $late = (clone $lab)->whereDate('due_at', '<', today())->count();
            $soon = (clone $lab)->whereBetween('due_at', [today()->toDateString(), today()->addDays(3)->toDateString()])->count();

            $stats[] = Stat::make('Order lab terlambat', $late)
                ->description(match (true) {
                    $late > 0 => $late.' lewat jatuh tempo'.($soon > 0 ? ' · '.$soon.' jatuh tempo ≤ 3 hari' : ''),
                    $soon > 0 => $soon.' jatuh tempo ≤ 3 hari',
                    default => 'Belum diterima dari lab',
                })
                ->color($late > 0 || $soon > 0 ? 'warning' : 'gray')
                ->url(LabOrderResource::getUrl('index'));
        }

        return $stats;
    }
}
