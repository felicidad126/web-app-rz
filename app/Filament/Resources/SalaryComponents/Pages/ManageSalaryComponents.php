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
        return 'Nominal bawaan dipakai bila karyawan belum punya nominal sendiri. PPh 21 diisi manual per slip (hitung otomatis belum tersedia).';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
