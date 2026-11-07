<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Imports\SupplierImporter;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSuppliers extends ManageRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import CSV')
                ->importer(SupplierImporter::class)
                ->visible(fn (): bool => auth()->user()->can('create', Supplier::class)),
            CreateAction::make(),
        ];
    }
}
