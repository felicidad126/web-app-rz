<?php

namespace App\Filament\Resources\Visits;

use App\Enums\VisitStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Visit;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Clinic\VisitFlow;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

final class VisitActions
{
    public static function start(): Action
    {
        return Action::make('startVisit')
            ->label('Panggil ke kursi')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->color('gray')
            ->visible(fn (?Visit $record): bool => $record?->status === VisitStatus::Waiting)
            ->authorize(fn (?Visit $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(function (Visit $record): void {
                VisitFlow::start($record);
                Notification::make()->title($record->patient->name.' dipanggil ke kursi')->success()->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancelVisit')
            ->label('Batalkan kunjungan')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (?Visit $record): bool => $record !== null
                && in_array($record->status, [VisitStatus::Waiting, VisitStatus::InTreatment], true)
                && ! $record->isLocked())
            ->authorize(fn (?Visit $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(function (Visit $record): void {
                try {
                    VisitFlow::cancel($record);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function examine(): Action
    {
        return Action::make('examine')
            ->label('Pemeriksaan')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->url(fn (Visit $record): string => VisitResource::getUrl('examine', ['record' => $record]))
            ->visible(fn (?Visit $record): bool => $record !== null
                && $record->status !== VisitStatus::Cancelled
                && (auth()->user()?->can('View:MedicalRecord') ?? false));
    }

    /** Buka kasir untuk kunjungan ini (tagihan dibuat bila belum ada). */
    public static function checkout(): Action
    {
        return Action::make('checkout')
            ->label('Kasir')
            ->icon(Heroicon::OutlinedCalculator)
            ->visible(fn (?Visit $record): bool => $record !== null
                && in_array($record->status, [VisitStatus::InTreatment, VisitStatus::Done, VisitStatus::Billed], true)
                && (auth()->user()?->can('Create:Invoice') ?? false))
            ->action(function (Visit $record, Action $action): void {
                try {
                    $invoice = InvoiceBuilder::forVisit($record);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Tagihan belum bisa dibuat')->body($e->getMessage())->danger()->send();

                    return;
                }

                $action->redirect(InvoiceResource::getUrl($invoice->isDraft() ? 'edit' : 'view', ['record' => $invoice]));
            });
    }
}
