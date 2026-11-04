<?php

namespace App\Filament\Resources\Icd9cmCodes\Pages;

use App\Filament\Imports\Icd9cmCodeImporter;
use App\Filament\Resources\Icd9cmCodes\Icd9cmCodeResource;
use App\Models\Icd9cmCode;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIcd9cmCodes extends ManageRecords
{
    protected static string $resource = Icd9cmCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import CSV')
                ->importer(Icd9cmCodeImporter::class)
                ->visible(fn (): bool => auth()->user()->can('create', Icd9cmCode::class)),
            CreateAction::make(),
        ];
    }
}
