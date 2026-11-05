<?php

namespace App\Support;

/**
 * Penomoran gigi FDI (dua digit: kuadran + posisi).
 *
 * Gigi tetap: kuadran 1–4, posisi 1–8. Gigi susu: kuadran 5–8, posisi 1–5.
 */
final class Teeth
{
    /** Urutan tampilan odontogram nasional (baris atas ke bawah, kanan pasien di kiri layar). */
    public const ROWS = [
        ['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'],
        ['55', '54', '53', '52', '51', '61', '62', '63', '64', '65'],
        ['85', '84', '83', '82', '81', '71', '72', '73', '74', '75'],
        ['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'],
    ];

    private const QUADRANTS = [
        1 => 'kanan atas', 2 => 'kiri atas', 3 => 'kiri bawah', 4 => 'kanan bawah',
        5 => 'kanan atas', 6 => 'kiri atas', 7 => 'kiri bawah', 8 => 'kanan bawah',
    ];

    private const PERMANENT_NAMES = [
        1 => 'Insisif sentral', 2 => 'Insisif lateral', 3 => 'Kaninus', 4 => 'Premolar pertama',
        5 => 'Premolar kedua', 6 => 'Molar pertama', 7 => 'Molar kedua', 8 => 'Molar ketiga',
    ];

    private const PRIMARY_NAMES = [
        1 => 'Insisif sentral', 2 => 'Insisif lateral', 3 => 'Kaninus', 4 => 'Molar pertama', 5 => 'Molar kedua',
    ];

    public static function isValid(?string $tooth): bool
    {
        if ($tooth === null || ! preg_match('/^[1-8][1-8]$/', $tooth)) {
            return false;
        }

        [$quadrant, $position] = [(int) $tooth[0], (int) $tooth[1]];

        return $quadrant <= 4 || $position <= 5;
    }

    public static function isPermanent(string $tooth): bool
    {
        return (int) $tooth[0] <= 4;
    }

    public static function name(string $tooth): string
    {
        if (! self::isValid($tooth)) {
            return $tooth;
        }

        [$quadrant, $position] = [(int) $tooth[0], (int) $tooth[1]];
        $names = $quadrant <= 4 ? self::PERMANENT_NAMES : self::PRIMARY_NAMES;
        $suffix = $quadrant <= 4 ? '' : ' sulung';

        return $names[$position].$suffix.' '.self::QUADRANTS[$quadrant];
    }

    /** @return array<int, string> nomor => "36 · Molar pertama kiri bawah" (kunci angka: PHP mengubah "36" menjadi 36). */
    public static function options(): array
    {
        $options = [];

        foreach ([self::ROWS[0], self::ROWS[3], self::ROWS[1], self::ROWS[2]] as $row) {
            foreach ($row as $tooth) {
                $options[$tooth] = $tooth.' · '.self::name($tooth);
            }
        }

        return $options;
    }

    /** @return array<string, array<int, string>> */
    public static function groupedOptions(): array
    {
        $permanent = [];
        $primary = [];

        foreach (self::options() as $tooth => $label) {
            if (self::isPermanent((string) $tooth)) {
                $permanent[$tooth] = $label;
            } else {
                $primary[$tooth] = $label;
            }
        }

        ksort($permanent);
        ksort($primary);

        return ['Gigi tetap' => $permanent, 'Gigi susu' => $primary];
    }

    /** @return array<string, array<int, string>> Nomor saja (untuk kolom sempit), dikelompokkan tetap/susu. */
    public static function numberOptions(): array
    {
        return array_map(
            fn (array $group): array => array_combine(array_keys($group), array_map('strval', array_keys($group))),
            self::groupedOptions(),
        );
    }
}
