<?php

namespace App\Services\Clinic;

/**
 * OHI-S (Oral Hygiene Index Simplified, Greene & Vermillion 1964).
 *
 * Enam gigi indeks dan permukaannya: 16 bukal, 11 labial, 26 bukal, 36 lingual, 31 labial, 46 lingual.
 * Skor debris (DI) dan kalkulus (CI) 0–3 per gigi. DI-S = ΣDI ÷ jumlah gigi yang diperiksa, CI-S sama,
 * OHI-S = DI-S + CI-S. Kategori: baik 0,0–1,2 · sedang 1,3–3,0 · buruk 3,1–6,0.
 * Gigi indeks yang hilang dilewati (rata-rata dari gigi yang diskor).
 */
final class OralHygiene
{
    /** @var array<int, string> gigi indeks => permukaan */
    public const INDEX_TEETH = ['16' => 'bukal', '11' => 'labial', '26' => 'bukal', '36' => 'lingual', '31' => 'labial', '46' => 'lingual'];

    /**
     * @param  array<int|string, array{debris?: mixed, calculus?: mixed}>|null  $scores
     * @return array{debris_index: float|null, calculus_index: float|null, ohis: float|null, category: string|null}
     */
    public static function calculate(?array $scores): array
    {
        $debris = [];
        $calculus = [];

        foreach (array_keys(self::INDEX_TEETH) as $tooth) {
            $row = $scores[$tooth] ?? $scores[(int) $tooth] ?? [];

            if (self::valid($row['debris'] ?? null)) {
                $debris[] = (int) $row['debris'];
            }

            if (self::valid($row['calculus'] ?? null)) {
                $calculus[] = (int) $row['calculus'];
            }
        }

        $di = $debris === [] ? null : round(array_sum($debris) / count($debris), 2);
        $ci = $calculus === [] ? null : round(array_sum($calculus) / count($calculus), 2);
        $ohis = $di === null && $ci === null ? null : round(($di ?? 0) + ($ci ?? 0), 2);

        return ['debris_index' => $di, 'calculus_index' => $ci, 'ohis' => $ohis, 'category' => self::category($ohis)];
    }

    public static function category(?float $ohis): ?string
    {
        return match (true) {
            $ohis === null => null,
            $ohis <= 1.2 => 'Baik',
            $ohis <= 3.0 => 'Sedang',
            default => 'Buruk',
        };
    }

    public static function format(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 2, ',', '.');
    }

    private static function valid(mixed $score): bool
    {
        return $score !== null && $score !== '' && is_numeric($score) && (int) $score >= 0 && (int) $score <= 3;
    }
}
