<?php

use App\Http\Controllers\BillingPrintController;
use App\Http\Controllers\ClinicalFileController;
use App\Http\Controllers\OdontogramPrintController;
use App\Http\Controllers\PayrollExportController;
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

// File rekam medis di disk privat (bukan lewat /storage publik).
Route::middleware(['auth'])->prefix('berkas')->name('clinic.')->group(function (): void {
    Route::get('lampiran/{attachment}', [ClinicalFileController::class, 'attachment'])->name('attachments.show');
    Route::get('persetujuan/{consent}', [ClinicalFileController::class, 'consentPdf'])->name('consents.pdf');
    Route::get('odontogram/{patient}', OdontogramPrintController::class)->name('odontogram.pdf');
});

// Struk thermal & nota PDF.
Route::middleware(['auth'])->prefix('cetak')->name('billing.')->group(function (): void {
    Route::get('struk/{transaction}', [BillingPrintController::class, 'receipt'])->name('receipt');
    Route::get('tagihan/{invoice}/struk', [BillingPrintController::class, 'invoiceReceipt'])->name('invoice-receipt');
    Route::get('tagihan/{invoice}/nota.pdf', [BillingPrintController::class, 'invoicePdf'])->name('invoice-pdf');
});

// Berita acara mutasi stok.
Route::middleware(['auth'])->prefix('cetak/stok')->name('stock.')->group(function (): void {
    Route::get('{movement}.pdf', [StockPrintController::class, 'movementPdf'])->name('movement-pdf');
});

// Slip & rekap jasa dokter / gaji (Fase 6).
Route::middleware(['auth'])->name('payroll.')->group(function (): void {
    Route::get('cetak/jasa/{payout}.pdf', [PayrollExportController::class, 'commissionSlip'])->name('commission-slip');
    Route::get('unduh/jasa/{period}.xlsx', [PayrollExportController::class, 'commissionExcel'])->where('period', '\d{4}-\d{2}')->name('commission-excel');
    Route::get('cetak/gaji/{payslip}.pdf', [PayrollExportController::class, 'payslipPdf'])->name('payslip-pdf');
    Route::get('unduh/gaji/{period}.xlsx', [PayrollExportController::class, 'payslipExcel'])->where('period', '\d{4}-\d{2}')->name('payslip-excel');
});

// Laporan (Fase 6): ekspor Excel/PDF.
Route::middleware(['auth'])->get('unduh/laporan', ReportExportController::class)->name('reports.export');
