<?php

namespace App\Filament\Resources\Invoices\Pages\Concerns;

use App\Models\Invoice;
use App\Models\Prescription;
use Filament\Notifications\Notification;

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

        Prescription::query()->where('visit_id', $invoice->visit_id)->whereNull('dispensed_at')
            ->update(['dispensed_at' => now(), 'dispensed_by' => auth()->id()]);

        Notification::make()->title('Resep ditandai sudah diserahkan')->success()->send();
    }
}
