<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Str;

/**
 * Judul halaman, breadcrumb, dan tombol memakai huruf kalimat seperti mockup, bukan Title Case bawaan Filament:
 * menu & judul daftar "Order lab", judul halaman "Buat order lab" / "Ubah janji temu" (label tunggal dipakai
 * setelah kata kerja, jadi tetap huruf kecil).
 */
trait SentenceCaseLabels
{
    public static function getTitleCaseModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
