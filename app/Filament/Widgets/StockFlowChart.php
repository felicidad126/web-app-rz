<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\OnStockDashboard;
use App\Services\Stock\StockDashboard;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Grafik pemakaian & penerimaan (harga pokok) per minggu atau per bulan. Chart.js bawaan Filament, tanpa CDN. */
class StockFlowChart extends ChartWidget
{
    use OnStockDashboard;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 1, 'xl' => 8];

    protected ?string $heading = 'Pemakaian & penerimaan';

    protected ?string $description = 'Nilai harga pokok dari mutasi yang sudah tercatat; mutasi yang dibatalkan tidak dihitung.';

    protected ?string $maxHeight = '260px';

    public ?string $filter = 'minggu';

    protected function getFilters(): ?array
    {
        return ['minggu' => '8 minggu', 'bulan' => '6 bulan'];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = StockDashboard::flow($this->branchId(), $this->filter === 'bulan' ? 'months' : 'weeks');
        // Periode berjalan belum selesai: batangnya dipudarkan.
        $fade = fn (string $color, string $faded): array => array_map(fn (array $row): string => $row['current'] ? $faded : $color, $rows);

        return [
            'labels' => array_map(fn (array $row): string => $row['label'], $rows),
            'datasets' => [
                [
                    'label' => 'Pemakaian',
                    'data' => array_map(fn (array $row): int => $row['usage'], $rows),
                    'backgroundColor' => $fade('#6c5dd3', 'rgba(108, 93, 211, .45)'),
                    'borderWidth' => 0,
                    'borderRadius' => 6,
                    'maxBarThickness' => 18,
                ],
                [
                    'label' => 'Penerimaan barang',
                    'data' => array_map(fn (array $row): int => $row['receipt'], $rows),
                    'backgroundColor' => $fade('#ada2f5', 'rgba(173, 162, 245, .45)'),
                    'borderWidth' => 0,
                    'borderRadius' => 6,
                    'maxBarThickness' => 18,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10, color: '#3f4157' } },
                    tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.y) } },
                },
                scales: {
                    y: { beginAtZero: true, border: { display: false }, grid: { color: '#eeeef5' },
                         ticks: { color: '#6b6c80', callback: (value) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value) } },
                    x: { grid: { display: false }, ticks: { color: '#6b6c80', maxRotation: 0, autoSkip: true } },
                },
            }
            JS);
    }
}
