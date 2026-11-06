<?php

namespace App\Filament\Resources\LabOrders\Pages;

use App\Filament\Resources\LabOrders\LabOrderResource;
use Filament\Resources\Pages\EditRecord;

class EditLabOrder extends EditRecord
{
    protected static string $resource = LabOrderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Pasien & cabang tidak berubah setelah order dibuat.
        unset($data['patient_id'], $data['branch_id']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return LabOrderResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
