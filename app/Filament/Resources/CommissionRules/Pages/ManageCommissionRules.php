<?php

namespace App\Filament\Resources\CommissionRules\Pages;

use App\Filament\Resources\CommissionRules\CommissionRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCommissionRules extends ManageRecords
{
    protected static string $resource = CommissionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah aturan'),
        ];
    }
}
