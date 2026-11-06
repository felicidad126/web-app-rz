<?php

namespace App\Filament\Resources\SalaryComponents\Pages;

use App\Filament\Resources\SalaryComponents\SalaryComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSalaryComponents extends ManageRecords
{
    protected static string $resource = SalaryComponentResource::class;

    public function getSubheading(): ?string
    {
        return 'PPh 21 diisi manual per slip (perhitungan otomatis masuk backlog setelah divalidasi konsultan pajak).';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
