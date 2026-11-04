<?php

namespace App\Filament\Resources\Icd10Codes\Pages;

use App\Filament\Imports\Icd10CodeImporter;
use App\Filament\Resources\Icd10Codes\Icd10CodeResource;
use App\Models\Icd10Code;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIcd10Codes extends ManageRecords
{
    protected static string $resource = Icd10CodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import CSV')
                ->importer(Icd10CodeImporter::class)
                ->visible(fn (): bool => auth()->user()->can('create', Icd10Code::class)),
            CreateAction::make(),
        ];
    }
}
