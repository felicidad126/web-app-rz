<?php

namespace App\Filament\Widgets;

use App\Enums\Kpi;
use App\Services\Reports\Kpis;
use App\Support\BranchContext;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

/** Rencana disetujui yang belum dijadwalkan (untuk ditindaklanjuti resepsionis). */
class UnscheduledPlansWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.simple-table';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $plans = Kpis::unscheduledPlans(BranchContext::available()->pluck('id')->all());
        $shown = $plans->take(8);
        $rest = $plans->slice(8);

        return [
            'heading' => 'Rencana disetujui, belum dijadwalkan',
            'description' => Kpi::UnscheduledTreatment->definition(),
            'columns' => [['label' => 'Pasien'], ['label' => 'Rencana'], ['label' => 'Disetujui'], ['label' => 'Nilai', 'align' => 'end']],
            'rows' => $shown->map(fn (array $row): array => [
                ['text' => $row['plan']->patient->name, 'sub' => $row['plan']->branch?->name],
                $row['plan']->title,
                $row['plan']->decided_at?->format('d/m/Y') ?? '—',
                Money::format($row['value'], false),
            ])->all(),
            'footer' => $plans->isEmpty() ? [] : [$rest->isEmpty() ? 'Total' : 'Dan '.$rest->count().' pasien lain', '', '', Money::format((int) $plans->sum('value'), false)],
            'empty' => 'Semua rencana yang disetujui sudah dijadwalkan.',
        ];
    }
}
