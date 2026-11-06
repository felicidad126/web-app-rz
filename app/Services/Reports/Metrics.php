<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\DailyMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Membaca KPI untuk rentang tanggal: hari yang sudah lewat dari snapshot `daily_metrics`, hari ini dihitung langsung.
 * Snapshot yang belum ada (mis. job belum jalan) dibuat saat dibaca, dibatasi agar halaman tetap cepat; sisanya diisi
 * job malam atau `php artisan metrics:snapshot --from=...`.
 */
final class Metrics
{
    /** Batas (tanggal × cabang) yang dihitung saat halaman dibuka. */
    public const LAZY_LIMIT = 70;

    /**
     * Σ satu metrik.
     *
     * @param  list<int>|null  $branchIds  null = semua cabang
     */
    public static function sum(string $metric, Carbon $from, Carbon $to, ?array $branchIds = null, ?int $practitionerId = null): int
    {
        return (int) self::grouped($metric, $from, $to, $branchIds, $practitionerId, 'all')->sum();
    }

    /**
     * Σ metrik dikelompokkan per 'branch' | 'practitioner' | 'date' | 'month' | 'all'.
     *
     * @param  list<int>|null  $branchIds
     * @return Collection<int|string, int>
     */
    public static function grouped(string $metric, Carbon $from, Carbon $to, ?array $branchIds = null, ?int $practitionerId = null, string $by = 'branch'): Collection
    {
        $branchIds ??= Branch::query()->pluck('id')->all();
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $yesterday = today()->subDay();
        $result = collect();

        if ($from->lte($yesterday)) {
            $pastTo = $to->lt($yesterday) ? $to : $yesterday;
            self::ensure($from, $pastTo, $branchIds);

            $key = match ($by) {
                'branch' => 'branch_id',
                'practitioner' => 'practitioner_id',
                'date' => 'date',
                default => null,
            };

            $rows = DailyMetric::query()->where('metric', $metric)->whereIn('branch_id', $branchIds)
                ->whereDate('date', '>=', $from)->whereDate('date', '<=', $pastTo)
                ->when($practitionerId !== null, fn ($q) => $q->where('practitioner_id', $practitionerId))
                ->get(['date', 'branch_id', 'practitioner_id', 'value']);

            foreach ($rows as $row) {
                $group = match ($by) {
                    'month' => $row->date->format('Y-m'),
                    'all' => 'all',
                    'date' => $row->date->toDateString(),
                    default => (int) $row->getAttribute((string) $key),
                };
                $result[$group] = ($result[$group] ?? 0) + $row->value;
            }
        }

        if ($to->gte(today()) && $from->lte(today())) {
            foreach ($branchIds as $branchId) {
                foreach (DailyMetrics::compute(today(), $branchId) as $row) {
                    if ($row['metric'] !== $metric || ($practitionerId !== null && $row['practitioner_id'] !== $practitionerId)) {
                        continue;
                    }

                    $group = match ($by) {
                        'branch' => $branchId,
                        'practitioner' => (int) $row['practitioner_id'],
                        'date' => today()->toDateString(),
                        'month' => today()->format('Y-m'),
                        default => 'all',
                    };
                    $result[$group] = ($result[$group] ?? 0) + $row['value'];
                }
            }
        }

        return $result;
    }

    /**
     * Buat snapshot yang belum ada untuk rentang tanggal lampau (maks. LAZY_LIMIT pasang tanggal × cabang).
     *
     * @param  list<int>  $branchIds
     */
    public static function ensure(Carbon $from, Carbon $to, array $branchIds): void
    {
        $existing = DailyMetric::query()->where('metric', DailyMetrics::MARKER)->whereIn('branch_id', $branchIds)
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->get(['date', 'branch_id'])->map(fn (DailyMetric $m): string => $m->date->toDateString().':'.$m->branch_id)->flip();

        $budget = self::LAZY_LIMIT;

        for ($day = $to->copy(); $day->gte($from) && $budget > 0; $day->subDay()) {
            foreach ($branchIds as $branchId) {
                if (! isset($existing[$day->toDateString().':'.$branchId]) && $budget-- > 0) {
                    DailyMetrics::snapshot($day->copy(), [$branchId]);
                }
            }
        }
    }

    /** Rasio aman (null bila pembagi 0). */
    public static function ratio(int|float $numerator, int|float $denominator): ?float
    {
        return $denominator > 0 ? $numerator / $denominator : null;
    }

    public static function percent(?float $ratio): string
    {
        return $ratio === null ? '—' : number_format($ratio * 100, 0, ',', '.').'%';
    }
}
