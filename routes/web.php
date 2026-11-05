<?php

use App\Http\Controllers\ClinicalFileController;
use App\Http\Controllers\OdontogramPrintController;
use Illuminate\Support\Facades\Route;

// Panel Filament terpasang di root ("/"), lihat App\Providers\Filament\AdminPanelProvider.
// Route PWA (manifest & service worker) ada di public/ sebagai file statis.

// File rekam medis di disk privat (bukan lewat /storage publik).
Route::middleware(['auth'])->prefix('berkas')->name('clinic.')->group(function (): void {
    Route::get('lampiran/{attachment}', [ClinicalFileController::class, 'attachment'])->name('attachments.show');
    Route::get('persetujuan/{consent}', [ClinicalFileController::class, 'consentPdf'])->name('consents.pdf');
    Route::get('odontogram/{patient}', OdontogramPrintController::class)->name('odontogram.pdf');
});
