<?php

namespace App\Reports;

use App\Models\User;
use App\Services\Reports\Metrics;
use Illuminate\Support\Carbon;

/** Kunjungan & pasien baru per hari (dari snapshot KPI harian; hari ini dihitung langsung). */
class VisitsReport extends Report
{
    public static function key(): string
    {
        return 'kunjungan';
    }

    public static function label(): string
    {
        return 'Kunjungan & pasien baru';
    }

    public static function description(): string
    {
        return 'Per hari: kunjungan selesai, pasien baru (kunjungan selesai pertama), janji, tidak datang, dan rata-rata waktu tunggu.';
    }

    public static function canView(User $user): bool
    {
        return $user->can('View:FinancialReports');
    }

    public function columns(): array
    {
        return [
            ['label' => 'Tanggal', 'type' => 'text'], ['label' => 'Kunjungan selesai', 'type' => 'number'], ['label' => 'Pasien baru', 'type' => 'number'],
            ['label' => 'Janji', 'type' => 'number'], ['label' => 'Tidak datang', 'type' => 'number'], ['label' => 'Rata-rata tunggu (menit)', 'type' => 'number'],
        ];
    }

    public function rows(): array
    {
        $get = fn (string $metric) => Metrics::grouped($metric, $this->filters->from, $this->filters->to, $this->filters->branchIds, null, 'date');
        $visits = $get('visits');
        $new = $get('new_patients');
        $appointments = $get('appointments');
        $noShows = $get('no_shows');
        $waitMinutes = $get('wait_minutes');
        $waitCount = $get('wait_count');

        $rows = [];

        for ($day = $this->filters->from->copy(); $day->lte($this->filters->to); $day->addDay()) {
            $key = $day->toDateString();

            if (! isset($visits[$key]) && ! isset($appointments[$key])) {
                continue;
            }

            $rows[] = [
                Carbon::parse($key)->format('d/m/Y'), (int) ($visits[$key] ?? 0), (int) ($new[$key] ?? 0), (int) ($appointments[$key] ?? 0),
                (int) ($noShows[$key] ?? 0), ($waitCount[$key] ?? 0) > 0 ? (int) round($waitMinutes[$key] / $waitCount[$key]) : null,
            ];
        }

        return $rows;
    }

    public function totals(): ?array
    {
        return $this->sumColumns($this->rows(), [1, 2, 3, 4]);
    }
}
