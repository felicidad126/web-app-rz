<?php

namespace App\Filament\Resources\Practitioners\Pages;

use App\Filament\Clinic\SatuSehatActions;
use App\Filament\Resources\Practitioners\PractitionerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPractitioner extends EditRecord
{
    protected static string $resource = PractitionerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SatuSehatActions::lookupPractitioner(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
