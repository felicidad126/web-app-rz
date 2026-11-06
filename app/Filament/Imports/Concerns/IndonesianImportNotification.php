<?php

namespace App\Filament\Imports\Concerns;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

trait IndonesianImportNotification
{
    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import selesai: '.Number::format($import->successful_rows).' baris berhasil.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' baris gagal (download file gagal untuk melihat alasannya).';
        }

        return $body;
    }
}
