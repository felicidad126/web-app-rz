<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Periode dashboard: "bulan berjalan" = tanggal 1 s.d. kemarin (snapshot 00:30), "bulan lalu" = bulan penuh.
 */
final class ReportPeriod
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly Carbon $month,
        public readonly string $label,
    ) {}

    public static function resolve(string $key): self
    {
        if ($key === 'last_month') {
            $month = now()->startOfMonth()->subMonthNoOverflow();

            return new self($month->copy(), $month->copy()->endOfMonth()->startOfDay(), $month, $month->locale('id')->translatedFormat('F Y'));
        }

        $month = now()->startOfMonth();
        $to = today()->subDay();

        return new self($month->copy(), $to, $month, $to->lt($month)
            ? 'Bulan berjalan · belum ada hari yang lewat'
            : 'Bulan berjalan, 1–'.$to->day.' '.$month->locale('id')->translatedFormat('F Y').' · data s.d. kemarin, diperbarui 00:30');
    }

    public function isEmpty(): bool
    {
        return $this->to->lt($this->from);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return ['mtd' => 'Bulan berjalan', 'last_month' => 'Bulan lalu'];
    }
}
