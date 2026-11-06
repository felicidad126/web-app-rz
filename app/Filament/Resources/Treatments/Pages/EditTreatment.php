<?php

namespace App\Filament\Resources\Treatments\Pages;

use App\Filament\Resources\Treatments\TreatmentResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTreatment extends EditRecord
{
    protected static string $resource = TreatmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DeleteAction::make(),
                RestoreAction::make(),
            ])
                ->label('Lainnya')
                ->color('gray')
                ->button(),
        ];
    }
}
