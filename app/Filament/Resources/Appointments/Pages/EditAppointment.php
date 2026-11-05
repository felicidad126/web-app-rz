<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Models\Appointment;
use Filament\Resources\Pages\EditRecord;

/** @property Appointment $record */
class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Appointment $record */
        $record = $this->getRecord();

        return AppointmentForm::fromRecord($record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AppointmentForm::toAttributes($data, $this->getRecord()->getKey());
    }

    protected function getHeaderActions(): array
    {
        return AppointmentActions::statusActions();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
