<?php

namespace App\Filament\Clinic;

use App\Models\Patient;
use App\Models\Practitioner;
use App\Services\SatuSehat\IhsLookup;
use App\Services\SatuSehat\SatuSehatException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Tombol cari IHS Number (SATUSEHAT) di halaman pasien & nakes. */
final class SatuSehatActions
{
    public static function lookupPatient(): Action
    {
        return Action::make('lookupIhs')
            ->label(fn (Patient $record): string => $record->satusehat_ihs_id ? 'Cek ulang IHS' : 'Cari IHS SATUSEHAT')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('gray')
            ->authorize(fn (Patient $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(fn (Patient $record) => self::run(fn () => app(IhsLookup::class)->patient($record, refresh: true), 'pasien'));
    }

    public static function lookupPractitioner(): Action
    {
        return Action::make('lookupIhs')
            ->label(fn (Practitioner $record): string => $record->satusehat_ihs_id ? 'Cek ulang IHS' : 'Cari IHS SATUSEHAT')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('gray')
            ->authorize(fn (Practitioner $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(fn (Practitioner $record, Action $action) => self::run(fn () => app(IhsLookup::class)->practitioner($record, refresh: true), 'nakes'));
    }

    private static function run(callable $lookup, string $who): void
    {
        try {
            $id = $lookup();
        } catch (SatuSehatException $e) {
            Notification::make()->title($e->issue->title())->body($e->getMessage().' '.$e->issue->fix())->danger()->persistent()->send();

            return;
        }

        Notification::make()->title("IHS {$who} ditemukan: {$id}")->success()->send();
    }
}
