<?php

namespace App\Filament\Resources\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Clinic\VisitFlow;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/** Aksi status janji temu, dipakai di tabel Janji temu dan papan Jadwal. */
final class AppointmentActions
{
    public static function confirm(): Action
    {
        return Action::make('confirmAppointment')
            ->label('Konfirmasi')
            ->icon(Heroicon::OutlinedCheck)
            ->color('gray')
            ->visible(fn (?Appointment $record): bool => $record?->status === AppointmentStatus::Booked)
            ->authorize(fn (?Appointment $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(function (Appointment $record): void {
                $record->update(['status' => AppointmentStatus::Confirmed, 'confirmed_at' => now()]);
                Notification::make()->title('Janji temu dikonfirmasi')->success()->send();
            });
    }

    public static function checkIn(): Action
    {
        return Action::make('checkInAppointment')
            ->label('Pasien datang')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->color('primary')
            ->visible(fn (?Appointment $record): bool => $record?->canCheckIn() ?? false)
            ->authorize(fn (?Appointment $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(function (Appointment $record): void {
                try {
                    $visit = VisitFlow::checkIn($record);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Masuk antrean nomor '.$visit->queue_number)
                    ->body($record->patient->name.' · '.$visit->visit_number)
                    ->success()
                    ->send();
            });
    }

    public static function noShow(): Action
    {
        return Action::make('noShowAppointment')
            ->label('Tidak datang')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Tandai tidak datang?')
            ->visible(fn (?Appointment $record): bool => $record !== null
                && in_array($record->status, [AppointmentStatus::Booked, AppointmentStatus::Confirmed], true)
                && $record->start_at->isPast())
            ->authorize(fn (?Appointment $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(fn (Appointment $record) => $record->update(['status' => AppointmentStatus::NoShow]));
    }

    public static function cancel(): Action
    {
        return Action::make('cancelAppointment')
            ->label('Batalkan')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->modalHeading('Batalkan janji temu')
            ->schema([
                TextInput::make('cancel_reason')
                    ->label('Alasan')
                    ->placeholder('Mis. pasien minta ganti hari')
                    ->required()
                    ->maxLength(255),
            ])
            ->visible(fn (?Appointment $record): bool => $record !== null
                && in_array($record->status, [AppointmentStatus::Booked, AppointmentStatus::Confirmed], true))
            ->authorize(fn (?Appointment $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(function (Appointment $record, array $data): void {
                $record->update([
                    'status' => AppointmentStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancel_reason' => $data['cancel_reason'],
                ]);
                Notification::make()->title('Janji temu dibatalkan')->send();
            });
    }

    /** @return list<Action> */
    public static function statusActions(): array
    {
        return [self::confirm(), self::checkIn(), self::noShow(), self::cancel()];
    }
}
