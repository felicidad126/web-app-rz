<?php

namespace App\Filament\Resources\Invoices\Pages\Concerns;

use App\Models\Invoice;
use App\Models\Prescription;
use App\Services\Stock\StockLedger;
use Filament\Notifications\Notification;
use Throwable;

/** Tombol "Sudah" di daftar "Sebelum pasien pulang": resep sudah diserahkan ke pasien. */
trait MarksPrescriptionDispensed
{
    public function markDispensed(): void
    {
        abort_unless(auth()->user()?->can('Update:Invoice'), 403);

        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        if ($invoice->visit_id === null) {
            return;
        }

        foreach (Prescription::query()->where('visit_id', $invoice->visit_id)->whereNull('dispensed_at')->get() as $prescription) {
            $prescription->forceFill(['dispensed_at' => now(), 'dispensed_by' => auth()->id()])->save();

            // Fase 5: obat keluar dari stok (FEFO) saat resep diserahkan.
            try {
                StockLedger::dispensePrescription($prescription, auth()->user());
            } catch (Throwable $e) {
                report($e);
            }
        }

        Notification::make()->title('Resep ditandai sudah diserahkan')->success()->send();
    }
}
