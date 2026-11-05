<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceActions;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\Concerns\MarksPrescriptionDispensed;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\CashSessions;
use App\Services\Billing\InvoiceBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Halaman kasir untuk draf tagihan: ubah baris & diskon, lalu terima pembayaran.
 */
class EditInvoice extends EditRecord
{
    use MarksPrescriptionDispensed;

    protected static string $resource = InvoiceResource::class;

    protected static ?string $breadcrumb = 'Kasir';

    public function form(Schema $schema): Schema
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();
        $invoice->loadMissing(['patient', 'visit.practitioner.user', 'branch']);

        return InvoiceForm::configure($schema, $invoice);
    }

    /** Pembayaran & refund tampil di halaman detail; draf belum punya keduanya. */
    public function getRelationManagers(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Kasir';
    }

    public function getSubheading(): ?string
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();
        $user = auth()->user();
        $session = $user instanceof User ? CashSessions::current($user, $invoice->branch_id) : null;

        return 'Draf tagihan · '.$invoice->branch->name.' · '.($session ? 'Sesi kasir '.$session->user->name.' · dibuka '.$session->opened_at->format('H:i') : 'sesi kasir belum dibuka');
    }

    protected function afterSave(): void
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();
        InvoiceBuilder::recalculate($invoice);
        $this->refreshFormData(['invoice_discount', 'payer_portion']);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Draf tagihan disimpan';
    }

    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction()->label('Simpan draf')];
    }

    protected function getHeaderActions(): array
    {
        return [
            InvoiceActions::receivePayment()
                ->mountUsing(function (?Schema $schema, Invoice $record): void {
                    // Simpan isian terakhir dulu agar yang dibayar sama dengan yang terlihat.
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $record->refresh();
                    $schema?->fill(InvoiceActions::paymentFormData($record));
                }),
            ActionGroup::make([
                InvoiceActions::issue()
                    ->before(fn () => $this->save(shouldRedirect: false, shouldSendSavedNotification: false)),
                InvoiceActions::refreshFromVisit(),
                Action::make('patient')
                    ->label('Data pasien')
                    ->url(fn (Invoice $record): string => PatientResource::getUrl('view', ['record' => $record->patient_id])),
                InvoiceActions::requestVoid(),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }
}
