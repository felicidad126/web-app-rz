<?php

namespace App\Filament\Widgets;

use App\Enums\Kpi;
use App\Services\Reports\Metrics;
use App\Support\BranchContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** Produksi per bulan, 12 bulan terakhir (bulan berjalan ditandai lebih terang karena belum penuh). */
class ProductionTrendWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return 'Produksi per bulan, semua cabang';
    }

    public function getDescription(): ?string
    {
        return 'Juta rupiah · '.Kpi::Production->definition();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn (int $i): Carbon => now()->startOfMonth()->subMonths($i));
        $values = Metrics::grouped('production', $months->first(), today(), BranchContext::available()->pluck('id')->all(), null, 'month');

        return [
            'datasets' => [[
                'label' => 'Produksi (juta Rp)',
                'data' => $months->map(fn (Carbon $m): float => round(($values[$m->format('Y-m')] ?? 0) / 1_000_000, 1))->all(),
                'backgroundColor' => $months->map(fn (Carbon $m): string => $m->isSameMonth(now()) ? 'rgba(13, 110, 99, 0.35)' : '#0d6e63')->all(),
                'borderRadius' => 4,
                'maxBarThickness' => 28,
            ]],
            'labels' => $months->map(fn (Carbon $m): string => $m->locale('id')->translatedFormat('M y'))->all(),
        ];
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'grid' => ['color' => '#f4f4f5']],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
