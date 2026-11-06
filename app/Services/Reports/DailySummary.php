<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Services\Stock\StockAlerts;
use Illuminate\Support\Carbon;

/** Ringkasan harian untuk email owner (Planning §5.8): produksi, kas, kunjungan, pasien baru, tidak datang, stok kritis. */
final class DailySummary
{
    /** @return array{date: Carbon, branches: list<array<string, mixed>>, total: array<string, int|null>} */
    public static function build(Carbon $date): array
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get()->map(function (Branch $branch) use ($date): array {
            $ids = [$branch->id];
            $sum = fn (string $metric): int => Metrics::sum($metric, $date, $date, $ids);

            return [
                'name' => $branch->name,
                'production' => $sum('production'),
                'target' => Targets::daily($date, $branch->id),
                'cash_in' => $sum('cash_in'),
                'visits' => $sum('visits'),
                'new_patients' => $sum('new_patients'),
                'appointments' => $sum('appointments'),
                'no_shows' => $sum('no_shows'),
                'no_show_value' => $sum('no_show_value'),
                'below_minimum' => StockAlerts::belowMinimum($branch->id)->count(),
                'expiring' => StockAlerts::expiring($branch->id)->count(),
                'negative' => StockAlerts::negative($branch->id)->count(),
            ];
        })->all();

        $total = [];

        foreach (['production', 'cash_in', 'visits', 'new_patients', 'appointments', 'no_shows', 'no_show_value'] as $key) {
            $total[$key] = (int) array_sum(array_column($branches, $key));
        }

        $targets = array_filter(array_column($branches, 'target'));
        $total['target'] = $targets === [] ? null : (int) array_sum($targets);

        return ['date' => $date, 'branches' => $branches, 'total' => $total];
    }
}
