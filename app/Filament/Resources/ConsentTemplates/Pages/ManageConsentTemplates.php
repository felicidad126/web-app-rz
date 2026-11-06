<?php

namespace App\Filament\Resources\ConsentTemplates\Pages;

use App\Filament\Resources\ConsentTemplates\ConsentTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageConsentTemplates extends ManageRecords
{
    protected static string $resource = ConsentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat template')];
    }
}
