<?php

namespace App\Filament\Resources\PatientPackages\Pages;

use App\Filament\Resources\PatientPackages\PatientPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPatientPackages extends ListRecords
{
    protected static string $resource = PatientPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Jual paket')];
    }
}
