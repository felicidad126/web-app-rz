<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Filament\Pages\Schedule;
use App\Filament\Resources\Visits\VisitResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('schedule')
                ->label('Papan jadwal & antrean')
                ->color('gray')
                ->url(Schedule::getUrl()),
        ];
    }
}
