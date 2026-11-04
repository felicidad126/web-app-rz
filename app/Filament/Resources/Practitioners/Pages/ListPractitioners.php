<?php

namespace App\Filament\Resources\Practitioners\Pages;

use App\Filament\Resources\Practitioners\PractitionerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPractitioners extends ListRecords
{
    protected static string $resource = PractitionerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
