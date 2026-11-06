<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Str;

/**
 * Judul halaman, breadcrumb, dan tombol memakai huruf kalimat ("Order lab", "Buat order lab") seperti mockup,
 * bukan Title Case bawaan Filament ("Order Lab").
 */
trait SentenceCaseLabels
{
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
