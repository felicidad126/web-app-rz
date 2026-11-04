<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

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
            EditAction::make(),
        ];
    }
}
