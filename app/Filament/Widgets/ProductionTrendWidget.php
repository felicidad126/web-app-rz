<?php

namespace App\Filament\Widgets;

use App\Enums\Kpi;
use App\Services\Reports\Metrics;
use App\Support\BranchContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Produksi per bulan, 12 bulan terakhir. Batang abu-abu (teal hanya untuk aksi utama); bulan lalu lebih gelap,
 * bulan berjalan putih bergaris karena belum penuh (mockup Dashboard).
 */
class ProductionTrendWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '220px';

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
                'backgroundColor' => $months->map(fn (Carbon $m): string => match (true) {
                    $m->isSameMonth(now()) => '#ffffff',
                    $m->isSameMonth(now()->subMonthNoOverflow()) => '#3f3f46',
                    default => '#a1a1aa',
                })->all(),
                'borderColor' => $months->map(fn (Carbon $m): string => $m->isSameMonth(now()) ? '#52525b' : 'transparent')->all(),
                'borderWidth' => 1,
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
                'x' => ['grid' => ['display' => false], 'ticks' => ['maxRotation' => 0, 'autoSkip' => true]],
            ],
        ];
    }
}
