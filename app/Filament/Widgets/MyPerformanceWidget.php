<?php

namespace App\Filament\Widgets;

use App\Enums\Kpi;
use App\Services\Reports\Kpis;
use App\Services\Reports\Metrics;
use App\Services\Reports\Targets;
use App\Support\BranchContext;
use App\Support\CommissionScope;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dashboard dokter: produksi & estimasi jasa saya (kemarin, bulan ini vs target), penerimaan rencana saya. */
class MyPerformanceWidget extends StatsOverviewWidget
{
    use HasWidgetShield {
        canView as shieldCanView;
    }

    protected static ?int $sort = 2;

    protected ?string $heading = 'Kinerja saya';

    public static function canView(): bool
    {
        return static::shieldCanView() && CommissionScope::ownPractitionerId(auth()->user()) !== null;
    }

    protected function getStats(): array
    {
        $pid = (int) CommissionScope::ownPractitionerId(auth()->user());
        $branches = BranchContext::available();
        $ids = $branches->pluck('id')->all();
        $yesterday = today()->subDay();
        $monthStart = now()->startOfMonth();

        $prodYesterday = Metrics::sum('production', $yesterday, $yesterday, $ids, $pid);
        $dailyTarget = $branches->sum(fn ($b) => Targets::daily($yesterday, $b->id, $pid) ?? 0);
        $prodMonth = $yesterday->lt($monthStart) ? 0 : Metrics::sum('production', $monthStart, $yesterday, $ids, $pid);
        $targetMonth = $branches->sum(fn ($b) => Targets::toDate($monthStart, $yesterday, $b->id, $pid) ?? 0);
        $attainment = $targetMonth > 0 ? Metrics::ratio($prodMonth, $targetMonth) : null;
        $commission = Metrics::sum('commission', $monthStart, today(), $ids, $pid);
        $acceptance = Kpis::caseAcceptance($monthStart, today(), $ids, $pid);
        $tip = fn (Kpi $k): array => ['title' => $k->getLabel().': '.$k->definition()];

        return [
            Stat::make('Produksi kemarin', Money::format($prodYesterday))
                ->description($dailyTarget > 0 ? 'Target harian '.Money::format($dailyTarget).' · '.Metrics::percent(Metrics::ratio($prodYesterday, $dailyTarget)) : 'Belum ada target')
                ->color($dailyTarget > 0 ? Kpi::attainmentColor(Metrics::ratio($prodYesterday, $dailyTarget)) : 'gray')
                ->extraAttributes($tip(Kpi::Production)),
            Stat::make('Produksi bulan ini', Money::format($prodMonth))
                ->description($targetMonth > 0 ? Metrics::percent($attainment).' dari target s.d. kemarin ('.Money::format($targetMonth).')' : 's.d. kemarin')
                ->color(Kpi::attainmentColor($attainment))
                ->extraAttributes($tip(Kpi::ProductionVsTarget)),
            Stat::make('Estimasi jasa bulan ini', Money::format($commission))
                ->description('Sebelum potongan; termasuk hari ini')
                ->extraAttributes($tip(Kpi::Commission)),
            Stat::make('Penerimaan rencana', Metrics::percent($acceptance['ratio']))
                ->description($acceptance['presented'] > 0 ? Money::format($acceptance['accepted']).' dari '.Money::format($acceptance['presented']) : 'Belum ada rencana diajukan bulan ini')
                ->extraAttributes($tip(Kpi::CaseAcceptance)),
        ];
    }
}
