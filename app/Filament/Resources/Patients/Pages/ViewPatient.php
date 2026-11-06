<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Clinic\SatuSehatActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Patients\PatientResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // UU PDP: catat setiap akses ke data pasien.
        activity('access')
            ->performedOn($this->getRecord())
            ->causedBy(auth()->user())
            ->event('viewed')
            ->log('Membuka data pasien');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('odontogram')
                ->label('Odontogram')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('View:MedicalRecord') ?? false)
                ->url(fn (): string => PatientResource::getUrl('odontogram', ['record' => $this->getRecord()])),
            Action::make('appointment')
                ->label('Buat janji')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('Create:Appointment') ?? false)
                ->url(fn (): string => AppointmentResource::getUrl('create', ['patient' => $this->getRecord()->getKey()])),
            SatuSehatActions::lookupPatient(),
            EditAction::make(),
        ];
    }
}
