<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DeleteAction::make()->hidden(fn (): bool => $this->getRecord()->is(auth()->user())),
                RestoreAction::make(),
            ])
                ->label('Lainnya')
                ->color('gray')
                ->button(),
        ];
    }
}
