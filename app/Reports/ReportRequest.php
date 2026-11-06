<?php

namespace App\Reports;

use App\Models\User;
use App\Services\Reports\Metrics;
use App\Support\BranchContext;
use App\Support\CommissionScope;
use App\Support\Money;
use Illuminate\Support\Carbon;

/** Menyusun laporan dari input halaman/URL dengan batasan izin yang sama (cabang yang boleh, dokter = diri sendiri). */
final class ReportRequest
{
    /** @param array<string, mixed> $input */
    public static function make(User $user, array $input): ?Report
    {
        $class = ReportRegistry::find((string) ($input['report'] ?? ''));

        if ($class === null || ! $class::canView($user)) {
            return null;
        }

        $available = BranchContext::available($user)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $branch = (int) ($input['branch'] ?? 0);
        $branchIds = $branch > 0 && in_array($branch, $available, true) ? [$branch] : $available;

        $practitionerId = $class::ownOnly($user)
            ? (CommissionScope::ownPractitionerId($user) ?? 0)
            : (filled($input['practitioner'] ?? null) && $class::supportsPractitioner() ? (int) $input['practitioner'] : null);

        $from = self::date($input['from'] ?? null) ?? now()->startOfMonth();
        $to = self::date($input['to'] ?? null) ?? today();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return new $class(new ReportFilters($from->startOfDay(), $to->startOfDay(), $branchIds, $practitionerId, $input['group'] ?? null));
    }

    public static function format(mixed $value, string $type): string
    {
        return match (true) {
            $value === null => '—',
            $type === 'money' => Money::format((int) $value, false),
            $type === 'percent' => Metrics::percent((float) $value),
            $type === 'number' => is_float($value) && floor($value) != $value
                ? rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',') : number_format((float) $value, 0, ',', '.'),
            default => (string) $value,
        };
    }

    /** Nilai untuk Excel: angka tetap angka (persen dalam %). */
    public static function excel(mixed $value, string $type): mixed
    {
        return match (true) {
            $value === null => null,
            $type === 'percent' => round((float) $value * 100, 1),
            default => $value,
        };
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::parse($value);
    }
}
