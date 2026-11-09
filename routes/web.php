<?php

use App\Http\Controllers\PwaController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\StockPrintController;
use Illuminate\Support\Facades\Route;

// Panel Filament terpasang di root ("/"), lihat App\Providers\Filament\AdminPanelProvider.
// PWA: service worker & ikon file statis di public/; manifest & halaman offline lewat route agar nama
// aplikasi diambil dari APP_NAME / APP_SHORT_NAME. Tanpa sesi & cookie (bisa di-cache service worker).
Route::withoutMiddleware('web')->group(function (): void {
    Route::get('manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('offline.html', [PwaController::class, 'offline'])->name('pwa.offline');
});

// Berita acara mutasi stok.
Route::middleware(['auth'])->prefix('cetak/stok')->name('stock.')->group(function (): void {
    Route::get('{movement}.pdf', [StockPrintController::class, 'movementPdf'])->name('movement-pdf');
});

// Laporan stok & kas pembelian: ekspor Excel/PDF/CSV.
Route::middleware(['auth'])->get('unduh/laporan', ReportExportController::class)->name('reports.export');
