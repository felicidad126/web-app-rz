<?php

namespace App\Filament\Widgets;

use App\Enums\Kpi;
use App\Enums\PractitionerType;
use App\Models\Practitioner;
use App\Services\Reports\Kpis;
use App\Services\Reports\Metrics;
use App\Services\Reports\Targets;
use App\Support\BranchContext;
use App\Support\Money;
use App\Support\ReportPeriod;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

/** Dashboard owner: per dokter, bulan berjalan (produksi, capaian target, penerimaan rencana, estimasi jasa). */
class DoctorPerformanceWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.simple-table';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $period = ReportPeriod::resolve('mtd');
        $branches = BranchContext::available();
        $ids = $branches->pluck('id')->all();
        $production = $period->isEmpty() ? collect() : Metrics::grouped('production', $period->from, $period->to, $ids, null, 'practitioner');
        $visits = $period->isEmpty() ? collect() : Metrics::grouped('visits', $period->from, $period->to, $ids, null, 'practitioner');
        $commission = $period->isEmpty() ? collect() : Metrics::grouped('commission', $period->from, $period->to, $ids, null, 'practitioner');

        $rows = Practitioner::query()->with(['user', 'schedules.branch'])
            ->whereIn('type', [PractitionerType::Dentist, PractitionerType::DentalSpecialist])
            ->get()
            ->filter(fn (Practitioner $p): bool => ($production[$p->id] ?? 0) > 0 || $p->schedules->whereIn('branch_id', $ids)->isNotEmpty())
            ->sortByDesc(fn (Practitioner $p): int => $production[$p->id] ?? 0)
            ->map(function (Practitioner $p) use ($production, $visits, $commission, $period, $ids, $branches): array {
                $prod = (int) ($production[$p->id] ?? 0);
                $target = $branches->sum(fn ($b) => Targets::toDate($period->month, $period->to, $b->id, $p->id) ?? 0);
                $attainment = $target > 0 ? Metrics::ratio($prod, $target) : null;
                $acceptance = Kpis::caseAcceptance($period->month, today(), $ids, $p->id);

                return [
                    ['text' => $p->displayName(), 'sub' => $p->schedules->pluck('branch.name')->unique()->implode(', ')],
                    Money::format($prod, false),
                    ['text' => Metrics::percent($attainment), 'color' => $attainment === null ? 'gray' : Kpi::attainmentColor($attainment)],
                    (string) ($visits[$p->id] ?? 0),
                    ['text' => Metrics::percent($acceptance['ratio']), 'sub' => $acceptance['presented'] > 0 ? 'dari '.Money::format($acceptance['presented']) : null],
                    Money::format((int) ($commission[$p->id] ?? 0), false),
                ];
            })->values()->all();

        return [
            'heading' => 'Per dokter, bulan berjalan',
            'description' => 'Data s.d. kemarin',
            'columns' => [
                ['label' => 'Dokter'],
                ['label' => 'Produksi', 'align' => 'end', 'tip' => Kpi::Production->definition()],
                ['label' => 'Capaian', 'align' => 'end', 'tip' => Kpi::ProductionVsTarget->definition()],
                ['label' => 'Kunjungan', 'align' => 'end'],
                ['label' => 'Penerimaan rencana', 'align' => 'end', 'tip' => Kpi::CaseAcceptance->definition()],
                ['label' => 'Est. jasa', 'align' => 'end', 'tip' => Kpi::Commission->definition()],
            ],
            'rows' => $rows,
            'note' => 'Penerimaan rencana = nilai rencana disetujui ÷ nilai rencana diajukan (bulan berjalan).',
            'empty' => 'Belum ada dokter dengan jadwal atau produksi.',
        ];
    }
}
