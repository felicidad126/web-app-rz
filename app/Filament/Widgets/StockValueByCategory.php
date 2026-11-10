<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\OnStockDashboard;
use App\Services\Stock\StockDashboard;
use App\Support\Money;
use Filament\Widgets\Widget;

/** Donat nilai persediaan per kategori + daftar nilai (tanpa JS; total = nilai persediaan halaman Stok). */
class StockValueByCategory extends Widget
{
    use OnStockDashboard;

    /** Warna irisan berurutan dari terbesar; teks di daftar tetap memakai warna teks biasa. */
    private const COLORS = ['#11142d', '#6c5dd3', '#9d93f0', '#c9c3ff', '#8a8ca2', '#d3d4de', '#e9e9f1'];

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 1, 'xl' => 4];

    protected string $view = 'filament.widgets.stock-value-by-category';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $rows = StockDashboard::valueByCategory($this->branchId());
        $total = array_sum(array_column($rows, 'value'));
        $stops = [];
        $from = 0.0;

        foreach ($rows as $i => $row) {
            $share = $total > 0 ? $row['value'] / $total * 100 : 0;
            $rows[$i]['color'] = self::COLORS[$i] ?? self::COLORS[array_key_last(self::COLORS)];
            $rows[$i]['percent'] = (int) round($share);
            $rows[$i]['formatted'] = Money::format($row['value'], false);
            $to = $from + $share;
            // Celah putih tipis antar-irisan agar warna yang mirip tetap terbedakan.
            $stops[] = $rows[$i]['color'].' '.round($from, 2).'% '.round(max($from, $to - 0.4), 2).'%';
            $stops[] = '#ffffff '.round(max($from, $to - 0.4), 2).'% '.round($to, 2).'%';
            $from = $to;
        }

        return [
            'rows' => $rows,
            'total' => Money::format($total),
            'totalShort' => $this->short($total),
            'gradient' => $stops === [] ? '#eeeef5 0 100%' : implode(', ', $stops),
        ];
    }

    private function short(int $amount): string
    {
        return match (true) {
            $amount >= 1_000_000_000 => 'Rp '.str_replace('.', ',', (string) round($amount / 1_000_000_000, 2)).' M',
            $amount >= 1_000_000 => 'Rp '.str_replace('.', ',', (string) round($amount / 1_000_000, 2)).' jt',
            default => Money::format($amount),
        };
    }
}
