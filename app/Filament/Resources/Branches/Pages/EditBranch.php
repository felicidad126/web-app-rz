<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use App\Models\Branch;
use App\Services\SatuSehat\LocationRegistrar;
use App\Services\SatuSehat\SatuSehatException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registerLocation')
                ->label('Daftarkan ke SATUSEHAT')
                ->icon(Heroicon::OutlinedMapPin)
                ->color('gray')
                ->visible(fn (Branch $record): bool => blank($record->satusehat_location_id))
                ->authorize(fn (Branch $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->requiresConfirmation()
                ->modalDescription('Membuat Location "Poli Gigi" untuk cabang ini di SATUSEHAT, lalu menyimpan ID-nya. Bila klinik sudah punya Location ID dari SATUSEHAT Platform, cukup isi kolomnya saja.')
                ->action(function (Branch $record): void {
                    try {
                        $id = app(LocationRegistrar::class)->register($record);
                    } catch (SatuSehatException $e) {
                        Notification::make()->title($e->issue->title())->body($e->getMessage().' '.$e->issue->fix())->danger()->persistent()->send();

                        return;
                    }

                    $this->refreshFormData(['satusehat_location_id']);
                    Notification::make()->title('Location terdaftar: '.$id)->success()->send();
                }),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
