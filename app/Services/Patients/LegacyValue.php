<?php

namespace App\Services\Patients;

use App\Enums\Gender;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Perapih nilai dari Excel/CSV sistem lama: tanggal berformat bebas (nama hari & bulan Indonesia, serial Excel),
 * NIK yang rusak karena disimpan sebagai angka, nomor HP tanpa angka 0 di depan, umur "34 th", dan sebagainya.
 *
 * Fungsi yang bisa gagal mengembalikan [nilai, masalah]: nilai null + pesan bila data tidak bisa dipakai.
 */
final class LegacyValue
{
    /** @var array<string, int> */
    private const MONTHS = [
        'januari' => 1, 'jan' => 1, 'january' => 1,
        'februari' => 2, 'feb' => 2, 'pebruari' => 2, 'february' => 2,
        'maret' => 3, 'mar' => 3, 'march' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5, 'may' => 5,
        'juni' => 6, 'jun' => 6, 'june' => 6,
        'juli' => 7, 'jul' => 7, 'july' => 7,
        'agustus' => 8, 'agu' => 8, 'agt' => 8, 'ags' => 8, 'aug' => 8, 'august' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10, 'oct' => 10, 'october' => 10,
        'november' => 11, 'nov' => 11, 'nopember' => 11,
        'desember' => 12, 'des' => 12, 'dec' => 12, 'december' => 12,
    ];

    private const DAY_NAMES = '(senin|selasa|rabu|kamis|jum\'?at|sabtu|minggu|ahad|monday|tuesday|wednesday|thursday|friday|saturday|sunday)';

    public static function text(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $text === '' ? null : $text;
    }

    /** Nomor RM lama: spasi di awal/tengah dibuang, huruf kapital ("mr 000123" → "MR000123"). */
    public static function rm(mixed $value): ?string
    {
        $text = self::text($value);

        return $text === null ? null : mb_strtoupper(str_replace(' ', '', $text));
    }

    /**
     * NIK 16 digit. Sel angka di Excel hanya menyimpan 15 digit, jadi NIK yang tersimpan sebagai angka
     * (atau berformat ilmiah 3.27E+15) dianggap rusak dan harus dikumpulkan ulang dari KTP/KK.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function nik(mixed $value): array
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return [null, null];
        }

        if (is_int($value) || is_float($value)) {
            return [null, 'NIK tersimpan sebagai angka di Excel (digit terakhir hilang); kumpulkan ulang dari KTP/KK'];
        }

        $text = (string) $value;

        if (preg_match('/e\+?\d+/i', $text)) {
            return [null, 'NIK berformat ilmiah (mis. 3,27E+15); kumpulkan ulang dari KTP/KK'];
        }

        $digits = preg_replace('/\D+/', '', $text) ?? '';

        if (strlen($digits) !== 16) {
            return [null, 'NIK '.strlen($digits).' digit, bukan 16; kumpulkan ulang dari KTP/KK'];
        }

        if (self::nikBirth($digits) === null || substr($digits, 12) === '0000' || (int) substr($digits, 0, 2) < 11) {
            return [null, 'Pola NIK tidak valid (kode wilayah/tanggal/nomor urut); kumpulkan ulang dari KTP/KK'];
        }

        return [$digits, null];
    }

    /**
     * Tanggal lahir & jenis kelamin yang tertanam di NIK (digit 7–12: DDMMYY, DD + 40 untuk perempuan).
     * Tahun 2 digit punya dua kemungkinan abad; keduanya dikembalikan (yang tidak di masa depan).
     *
     * @return array{gender: Gender, dates: list<Carbon>}|null
     */
    public static function nikBirth(string $nik): ?array
    {
        $day = (int) substr($nik, 6, 2);
        $month = (int) substr($nik, 8, 2);
        $year = (int) substr($nik, 10, 2);
        $gender = $day > 40 ? Gender::Female : Gender::Male;
        $day = $day > 40 ? $day - 40 : $day;
        $dates = [];

        foreach ([2000 + $year, 1900 + $year] as $fullYear) {
            if (checkdate($month, $day, $fullYear) && Carbon::create($fullYear, $month, $day)?->lte(today())) {
                $dates[] = Carbon::create($fullYear, $month, $day)->startOfDay();
            }
        }

        return $dates === [] ? null : ['gender' => $gender, 'dates' => $dates];
    }

    /**
     * Tanggal dari berbagai format: "Selasa, 22 April 2025", "Rabu,07/05/2025", "Kamis,03-Juli-2025",
     * "Minggu/26/10/2025", "2025-05-07", "07.05.25", sel tanggal Excel, atau serial Excel (45769).
     */
    public static function date(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d{5}(\.\d+)?$/', trim($value)))) {
            $serial = (int) floor((float) $value);

            // Serial Excel (1 = 1900-01-01). Angka 1900–2100 dianggap tahun saja, bukan tanggal.
            return $serial > 0 && $serial < 73051 && ($serial < 1900 || $serial > 2100)
                ? Carbon::create(1899, 12, 30)?->addDays($serial)->startOfDay()
                : null;
        }

        $text = mb_strtolower(self::text($value) ?? '');
        $text = trim((string) preg_replace('/^'.self::DAY_NAMES.'\b[\s,\/\-.]*/u', '', $text));

        if ($text === '') {
            return null;
        }

        if (preg_match('/^(\d{4})[\-\/.](\d{1,2})[\-\/.](\d{1,2})/', $text, $m)) {
            return self::makeDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('/^(\d{1,2})[\-\/.\s]+(\d{1,2})[\-\/.\s]+(\d{2}|\d{4})$/', $text, $m)) {
            return self::makeDate(self::year($m[3]), (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/^(\d{1,2})[\-\/.\s]+([a-z]+)[\-\/.,\s]+(\d{2}|\d{4})$/', $text, $m) && isset(self::MONTHS[$m[2]])) {
            return self::makeDate(self::year($m[3]), self::MONTHS[$m[2]], (int) $m[1]);
        }

        if (preg_match('/^([a-z]+)\s+(\d{1,2}),?\s+(\d{4})$/', $text, $m) && isset(self::MONTHS[$m[1]])) {
            return self::makeDate((int) $m[3], self::MONTHS[$m[1]], (int) $m[2]);
        }

        return null;
    }

    /** Umur dalam tahun: "34", "34 th", "34 tahun", "8 bulan" (→ 0). */
    public static function age(mixed $value): ?int
    {
        $text = mb_strtolower(self::text($value) ?? '');

        if (! preg_match('/^(\d{1,3})\s*([a-z]*)/', $text, $m)) {
            return null;
        }

        $age = in_array($m[2], ['bulan', 'bln', 'bl', 'minggu', 'hari'], true) ? 0 : (int) $m[1];

        return $age <= 120 ? $age : null;
    }

    public static function gender(mixed $value): ?Gender
    {
        return match (mb_strtolower(trim((string) self::text($value)))) {
            'l', 'lk', 'laki-laki', 'laki laki', 'lakilaki', 'laki', 'pria', 'male', 'm', 'cowok' => Gender::Male,
            'p', 'pr', 'perempuan', 'wanita', 'female', 'f', 'w', 'cewek' => Gender::Female,
            default => null,
        };
    }

    /**
     * Nomor HP Indonesia dirapikan ke awalan 0: "+62 812-3456-789" / "812345678" (angka 0 hilang di Excel) → "0812…".
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function phone(mixed $value): array
    {
        $text = self::text($value);

        if ($text === null) {
            return [null, null];
        }

        $digits = preg_replace('/\D+/', '', $text) ?? '';

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0'.$digits;
        }

        if (! preg_match('/^0\d{8,13}$/', $digits)) {
            return [null, 'Nomor HP tidak dikenali; dikosongkan'];
        }

        return [$digits, null];
    }

    private static function year(string $year): int
    {
        if (strlen($year) === 4) {
            return (int) $year;
        }

        $short = (int) $year;

        return $short <= (int) today()->format('y') ? 2000 + $short : 1900 + $short;
    }

    private static function makeDate(int $year, int $month, int $day): ?Carbon
    {
        return checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100
            ? Carbon::create($year, $month, $day)?->startOfDay()
            : null;
    }
}
