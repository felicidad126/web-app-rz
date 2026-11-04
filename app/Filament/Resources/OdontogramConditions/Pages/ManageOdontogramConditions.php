<?php

namespace App\Filament\Resources\OdontogramConditions\Pages;

use App\Filament\Resources\OdontogramConditions\OdontogramConditionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOdontogramConditions extends ManageRecords
{
    protected static string $resource = OdontogramConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
