<?php

namespace App\Filament\Resources\Treatments\Pages;

use App\Filament\Imports\TreatmentImporter;
use App\Filament\Resources\Treatments\TreatmentResource;
use App\Models\Treatment;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListTreatments extends ListRecords
{
    protected static string $resource = TreatmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import CSV')
                ->importer(TreatmentImporter::class)
                ->visible(fn (): bool => auth()->user()->can('create', Treatment::class)),
            CreateAction::make(),
        ];
    }
}
