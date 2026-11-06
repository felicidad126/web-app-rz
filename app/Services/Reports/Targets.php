<?php

namespace App\Services\Reports;

use App\Models\Target;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Target produksi bulanan per cabang atau per dokter di cabang (Planning §5.8). Target harian = target bulan ÷ hari kerja
 * bulan itu, dengan hari kerja diambil dari jadwal praktik (dikurangi cuti seharian).
 */
final class Targets
{
    /** @var array<string, Collection<int, string>> */
    private static array $days = [];

    public static function monthly(Carbon $month, int $branchId, ?int $practitionerId = null): ?int
    {
        $query = Target::query()->whereDate('period', $month->copy()->startOfMonth())->where('branch_id', $branchId)->where('metric', 'production');

        if ($practitionerId !== null) {
            return $query->where('practitioner_id', $practitionerId)->value('amount');
        }

        $branch = (clone $query)->whereNull('practitioner_id')->value('amount');

        if ($branch !== null) {
            return $branch;
        }

        // Tanpa target cabang: jumlah target dokter di cabang itu (bila ada).
        $sum = (int) $query->whereNotNull('practitioner_id')->sum('amount');

        return $sum > 0 ? $sum : null;
    }

    /** @return Collection<int, string> tanggal (Y-m-d) hari kerja */
    public static function workingDays(Carbon $month, int $branchId, ?int $practitionerId = null): Collection
    {
        $key = $month->format('Y-m').':'.$branchId.':'.($practitionerId ?? '*');

        return self::$days[$key] ??= collect(range(0, $month->daysInMonth - 1))
            ->map(fn (int $i): Carbon => $month->copy()->startOfMonth()->addDays($i))
            ->filter(fn (Carbon $day): bool => array_sum(DailyMetrics::scheduledMinutes($day, $branchId, $practitionerId)) > 0)
            ->map(fn (Carbon $day): string => $day->toDateString())
            ->values();
    }

    /** Target harian; null bila tidak ada target atau tanggal itu bukan hari kerja. */
    public static function daily(Carbon $date, int $branchId, ?int $practitionerId = null): ?int
    {
        $monthly = self::monthly($date, $branchId, $practitionerId);
        $days = self::workingDays($date, $branchId, $practitionerId);

        if ($monthly === null || $days->isEmpty() || ! $days->contains($date->toDateString())) {
            return null;
        }

        return (int) round($monthly / $days->count());
    }

    /** Target s.d. tanggal tertentu dalam bulan itu (target harian × hari kerja yang sudah lewat). */
    public static function toDate(Carbon $month, Carbon $asOf, int $branchId, ?int $practitionerId = null): ?int
    {
        $monthly = self::monthly($month, $branchId, $practitionerId);
        $days = self::workingDays($month, $branchId, $practitionerId);

        if ($monthly === null || $days->isEmpty()) {
            return null;
        }

        $elapsed = $days->filter(fn (string $day): bool => $day <= $asOf->toDateString())->count();

        return (int) round($monthly / $days->count() * $elapsed);
    }

    public static function flush(): void
    {
        self::$days = [];
    }
}
