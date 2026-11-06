<?php

namespace App\Filament\Resources\Targets\Pages;

use App\Filament\Resources\Targets\TargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTargets extends ManageRecords
{
    protected static string $resource = TargetResource::class;

    public function getSubheading(): ?string
    {
        return 'Target harian = target bulan ÷ hari kerja (hari yang punya jadwal praktik, dikurangi cuti seharian). Tanpa target cabang, dashboard memakai jumlah target dokter di cabang itu.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah target')];
    }
}
