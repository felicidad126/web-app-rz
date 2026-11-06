<?php

namespace App\Filament\Clinic;

use Illuminate\Support\HtmlString;

/**
 * Teks pendek berisi kode (No. RM, No. kunjungan, No. order) dalam huruf mono, untuk deskripsi kolom tabel
 * dan subjudul halaman. Bagian selain kode tetap huruf biasa. Semua bagian di-escape.
 */
final class CodeText
{
    /**
     * @param  list<array{0: string|null, 1: bool}|string|null>  $parts  string biasa, atau [teks, mono?]
     */
    public static function join(array $parts, string $separator = ' · '): HtmlString
    {
        $html = collect($parts)
            ->map(fn (array|string|null $part): ?string => match (true) {
                is_array($part) && filled($part[0]) => $part[1] ? '<span class="klinik-mono">'.e($part[0]).'</span>' : e($part[0]),
                is_string($part) && filled($part) => e($part),
                default => null,
            })
            ->filter()
            ->implode(e($separator));

        return new HtmlString($html);
    }

    public static function code(?string $code): HtmlString
    {
        return self::join([[$code, true]]);
    }
}
