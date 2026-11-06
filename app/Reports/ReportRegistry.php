<?php

namespace App\Reports;

use App\Models\User;

final class ReportRegistry
{
    /** @return list<class-string<Report>> */
    public static function all(): array
    {
        return [
            RevenueReport::class, CashFlowReport::class, ProfitReport::class, ReceivablesReport::class, CommissionReport::class,
            StockReport::class, ExpiryReport::class, VisitsReport::class, DiagnosisReport::class, NoShowReport::class,
        ];
    }

    /** @return array<string, string> laporan yang boleh dilihat pengguna */
    public static function options(User $user): array
    {
        return collect(self::all())->filter(fn (string $class): bool => $class::canView($user))
            ->mapWithKeys(fn (string $class): array => [$class::key() => $class::label()])->all();
    }

    /** @return class-string<Report>|null */
    public static function find(string $key): ?string
    {
        return collect(self::all())->first(fn (string $class): bool => $class::key() === $key);
    }
}
