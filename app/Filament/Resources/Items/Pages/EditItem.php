<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;

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
