<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use Filament\Resources\Pages\CreateRecord;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function afterFill(): void
    {
        // Prefill dari papan jadwal / recall: ?patient=..&date=..&time=..&chair=..&practitioner=..
        $prefill = array_filter([
            'patient_id' => request()->integer('patient') ?: null,
            'date' => request()->query('date'),
            'start_time' => request()->query('time'),
            'chair_id' => request()->integer('chair') ?: null,
            'practitioner_id' => request()->integer('practitioner') ?: null,
        ], fn ($value): bool => filled($value));

        if ($prefill !== []) {
            $this->form->fill([...$this->data ?? [], ...$prefill]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...AppointmentForm::toAttributes($data), 'status' => AppointmentStatus::Booked];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
