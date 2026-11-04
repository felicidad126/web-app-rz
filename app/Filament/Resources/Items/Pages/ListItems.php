<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Imports\ItemImporter;
use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import CSV')
                ->importer(ItemImporter::class)
                ->visible(fn (): bool => auth()->user()->can('create', Item::class)),
            CreateAction::make(),
        ];
    }
}
