<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceActions;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\Concerns\MarksPrescriptionDispensed;
use App\Models\Invoice;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewInvoice extends ViewRecord
{
    use MarksPrescriptionDispensed;

    protected static string $resource = InvoiceResource::class;

    public function infolist(Schema $schema): Schema
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();
        $invoice->loadMissing(['patient', 'branch', 'visit.practitioner.user', 'items', 'payer', 'voidRequester', 'voider']);

        return $schema
            ->columns(1)
            ->components([
                View::make('filament.clinic.patient-banner')->viewData(['patient' => $invoice->patient]),
                Grid::make(['default' => 1, 'lg' => 3])->schema([
                    Section::make(fn (): string => 'Tagihan '.$invoice->label())
                        ->description(fn (): string => ($invoice->visit ? 'Kunjungan '.$invoice->visit->visit_number.' · '.$invoice->visit->practitioner->user->name : 'Penjualan langsung')
                            .' · '.$invoice->status->getLabel())
                        ->columnSpan(['lg' => 2])
                        ->schema([View::make('filament.billing.invoice-lines')->viewData(['invoice' => $invoice])]),
                    View::make('filament.billing.checkout-side')->viewData(['invoice' => $invoice])->columnSpan(1),
                ]),
            ]);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        return $invoice->label();
    }

    public function getSubheading(): ?string
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        $parts = [$invoice->status->getLabel(), 'Total '.Money::format($invoice->total)];

        if ($invoice->isOutstanding()) {
            $parts[] = 'sisa '.Money::format($invoice->balance());
        }

        if ($invoice->void_requested_at && $invoice->status !== InvoiceStatus::Void) {
            $parts[] = 'menunggu persetujuan void';
        }

        return implode(' · ', $parts);
    }

    protected function getHeaderActions(): array
    {
        return [
            InvoiceActions::receivePayment(),
            EditAction::make()->label('Buka kasir')->visible(fn (Invoice $record): bool => InvoiceResource::canEdit($record)),
            InvoiceActions::printReceipt(),
            ActionGroup::make([
                InvoiceActions::printNote(),
                InvoiceActions::requestRefund(),
                InvoiceActions::requestVoid(),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }
}
