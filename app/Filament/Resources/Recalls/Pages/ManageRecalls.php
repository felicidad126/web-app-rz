<?php

namespace App\Filament\Resources\Recalls\Pages;

use App\Filament\Resources\Recalls\RecallResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRecalls extends ManageRecords
{
    protected static string $resource = RecallResource::class;

    /** Sama dengan label menu. */
    protected static ?string $title = 'Recall (kontrol)';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah recall')];
    }
}
