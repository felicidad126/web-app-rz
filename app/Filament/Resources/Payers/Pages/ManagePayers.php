<?php

namespace App\Filament\Resources\Payers\Pages;

use App\Filament\Resources\Payers\PayerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePayers extends ManageRecords
{
    protected static string $resource = PayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
