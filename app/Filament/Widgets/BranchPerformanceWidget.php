<?php

namespace App\Filament\Widgets;

use App\Enums\Kpi;
use App\Services\Reports\Kpis;
use App\Services\Reports\Metrics;
use App\Services\Reports\Targets;
use App\Support\BranchContext;
use App\Support\ReportPeriod;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

/** Dashboard owner: kinerja per cabang (mockup "Dashboard owner"). */
class BranchPerformanceWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.branch-performance';

    public string $period = 'mtd';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $period = ReportPeriod::resolve($this->period);
        $rows = [];

        foreach (BranchContext::available() as $branch) {
            $ids = [$branch->id];
            $production = $period->isEmpty() ? 0 : Metrics::sum('production', $period->from, $period->to, $ids);
            $cash = $period->isEmpty() ? 0 : Metrics::sum('cash_in', $period->from, $period->to, $ids);
            $target = Targets::toDate($period->month, $period->to, $branch->id);
            $appointments = $period->isEmpty() ? 0 : Metrics::sum('appointments', $period->from, $period->to, $ids);
            $noShows = $period->isEmpty() ? 0 : Metrics::sum('no_shows', $period->from, $period->to, $ids);

            $rows[] = [
                'name' => $branch->name,
                'production' => $production,
                'target' => $target,
                'attainment' => $target ? Metrics::ratio($production, $target) : null,
                'cash' => $cash,
                'cash_ratio' => Metrics::ratio($cash, $production),
                'new_patients' => $period->isEmpty() ? 0 : Metrics::sum('new_patients', $period->from, $period->to, $ids),
                'no_shows' => $noShows,
                'appointments' => $appointments,
                'unscheduled' => (int) Kpis::unscheduledPlans($ids)->sum('value'),
                'daily_target' => Targets::monthly($period->month, $branch->id) !== null && Targets::workingDays($period->month, $branch->id)->isNotEmpty()
                    ? (int) round(Targets::monthly($period->month, $branch->id) / Targets::workingDays($period->month, $branch->id)->count()) : null,
            ];
        }

        $collection = collect($rows);
        $targetSum = $collection->whereNotNull('target')->sum('target');
        $total = [
            'production' => (int) $collection->sum('production'),
            'target' => $targetSum > 0 ? (int) $targetSum : null,
            'cash' => (int) $collection->sum('cash'),
            'new_patients' => (int) $collection->sum('new_patients'),
            'no_shows' => (int) $collection->sum('no_shows'),
            'appointments' => (int) $collection->sum('appointments'),
            'unscheduled' => (int) $collection->sum('unscheduled'),
        ];
        $total['attainment'] = $total['target'] ? Metrics::ratio($total['production'], $total['target']) : null;
        $total['cash_ratio'] = Metrics::ratio($total['cash'], $total['production']);

        return ['rows' => $rows, 'total' => $total, 'periodLabel' => $period->label, 'kpi' => Kpi::class, 'options' => ReportPeriod::options()];
    }
}
