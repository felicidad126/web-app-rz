<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPatient extends EditRecord
{
    protected static string $resource = PatientResource::class;

    /** Tabel kepesertaan, kunjungan, dll. hanya di halaman Lihat; halaman Ubah fokus ke form. */
    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
