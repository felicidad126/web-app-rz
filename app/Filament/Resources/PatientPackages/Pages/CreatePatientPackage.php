<?php

namespace App\Filament\Resources\PatientPackages\Pages;

use App\Filament\Resources\PatientPackages\PatientPackageResource;
use App\Models\Package;
use App\Models\Patient;
use App\Services\Billing\PackageSales;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreatePatientPackage extends CreateRecord
{
    protected static string $resource = PatientPackageResource::class;

    protected static ?string $title = 'Jual paket';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return PackageSales::sell(Patient::findOrFail($data['patient_id']), Package::findOrFail($data['package_id']), $data);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Paket belum bisa dijual')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Jual paket');
    }

    protected function getRedirectUrl(): string
    {
        return PatientPackageResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
