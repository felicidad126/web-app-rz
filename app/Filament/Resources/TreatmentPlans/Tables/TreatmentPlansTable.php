<?php

namespace App\Filament\Resources\TreatmentPlans\Tables;

use App\Enums\TreatmentPlanStatus;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanActions;
use App\Models\TreatmentPlan;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TreatmentPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['patient', 'practitioner.user', 'items']))
            ->columns([
                TextColumn::make('patient.name')
                    ->label('Pasien')
                    ->description(fn (TreatmentPlan $record): string => $record->patient->rm_number)
                    ->searchable(['name', 'rm_number']),
                TextColumn::make('title')->label('Rencana')->searchable(),
                TextColumn::make('practitioner.user.name')->label('Dokter')->visibleFrom('md'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('total')
                    ->label('Estimasi')
                    ->state(fn (TreatmentPlan $record): int => $record->totalEstimate())
                    ->money('IDR', locale: 'id')
                    ->alignEnd(),
                TextColumn::make('created_at')->label('Dibuat')->date('d/m/Y')->sortable()->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(TreatmentPlanStatus::class)->multiple(),
                SelectFilter::make('practitioner_id')->label('Dokter')->options(fn (): array => PractitionerOptions::dentists()),
            ])
            ->recordActions([
                ActionGroup::make([...TreatmentPlanActions::all(), EditAction::make()]),
            ]);
    }
}
