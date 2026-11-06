<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Models\TreatmentPlan;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TreatmentPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'treatmentPlans';

    protected static ?string $title = 'Rencana perawatan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('ViewAny:TreatmentPlan') ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['practitioner.user', 'items']))
            ->recordUrl(fn (TreatmentPlan $record): string => TreatmentPlanResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('title')->label('Rencana'),
                TextColumn::make('practitioner.user.name')->label('Dokter'),
                TextColumn::make('status')->label('Status')->badge()->extraAttributes(['class' => 'klinik-status']),
                TextColumn::make('total')->label('Estimasi')->state(fn (TreatmentPlan $record): int => $record->totalEstimate())
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state, false))->alignEnd(),
                TextColumn::make('created_at')->label('Dibuat')->date('d/m/Y'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Buat rencana')
                    ->visible(fn (): bool => auth()->user()?->can('Create:TreatmentPlan') ?? false)
                    ->url(fn (): string => TreatmentPlanResource::getUrl('create', ['patient' => $this->getOwnerRecord()->getKey()])),
            ]);
    }
}
