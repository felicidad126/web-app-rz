<?php

namespace App\Services\Stock;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Tanggal kedaluwarsa dari kemasan. Banyak kemasan hanya mencantumkan bulan/tahun (mis. "08/2027"):
 * dinormalisasi ke hari terakhir bulan itu (aturan yang sama dengan daftar BHP expired klinik).
 */
final class ExpiryDate
{
    public static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $text = trim((string) $value);

        // Bulan/tahun saja: 08/2027, 8-2027, 2027-08.
        if (preg_match('#^(\d{1,2})[/\-.](\d{4})$#', $text, $m)) {
            return self::date($text, (int) $m[2], (int) $m[1], 1)->endOfMonth()->startOfDay();
        }

        if (preg_match('#^(\d{4})-(\d{1,2})$#', $text, $m)) {
            return self::date($text, (int) $m[1], (int) $m[2], 1)->endOfMonth()->startOfDay();
        }

        // Tanggal lengkap: 31/08/2027 atau 2027-08-31.
        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $text, $m)) {
            return self::date($text, (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $text, $m)) {
            return self::date($text, (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        throw self::invalid($text);
    }

    /** Tanggal yang benar-benar ada (Carbon::create diam-diam menggeser 13/2027 ke Januari 2028). */
    private static function date(string $text, int $year, int $month, int $day): Carbon
    {
        if (! checkdate($month, $day, $year) || $year < 2000 || $year > 2100) {
            throw self::invalid($text);
        }

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    private static function invalid(string $text): InvalidArgumentException
    {
        return new InvalidArgumentException("Tanggal kedaluwarsa \"{$text}\" tidak dikenali. Tulis seperti 08/2027 atau 31/08/2027.");
    }
}
