<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Imports\PatientImporter;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Patient;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListPatients extends ListRecords
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import CSV')
                ->importer(PatientImporter::class)
                ->visible(fn (): bool => auth()->user()->can('create', Patient::class)),
            CreateAction::make()->label('Pasien baru'),
        ];
    }
}
