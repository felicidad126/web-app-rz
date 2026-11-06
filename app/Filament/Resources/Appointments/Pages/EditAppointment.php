<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Clinic\StatusDot;
use App\Filament\Resources\Appointments\AppointmentActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Models\Appointment;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

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
        // Satu tombol utama per layar: "Simpan" di form; aksi status abu-abu.
        return [
            AppointmentActions::confirm(),
            AppointmentActions::checkIn()->color('gray'),
            AppointmentActions::noShow(),
            AppointmentActions::cancel(),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Appointment $record */
        $record = $this->getRecord();
        $record->loadMissing('visit');
        [$color, $status] = StatusDot::forAppointment($record, withTime: true);

        return StatusDot::html($color, $status);
    }

    /** Banner pasien (alergi, penjamin, saldo terutang) di atas form. */
    public function content(Schema $schema): Schema
    {
        /** @var Appointment $record */
        $record = $this->getRecord();

        return $schema->components([
            View::make('filament.clinic.patient-banner')->viewData(['patient' => $record->patient]),
            $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
