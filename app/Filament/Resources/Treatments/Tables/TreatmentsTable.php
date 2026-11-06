<?php

namespace App\Filament\Resources\Treatments\Tables;

use App\Enums\TreatmentCategory;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TreatmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Kode')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('name')->label('Tindakan')->searchable()->sortable()->wrap(),
                TextColumn::make('category')->label('Kategori')->badge()->color('gray')->visibleFrom('lg'),
                TextColumn::make('fee_group')->label('Jenis jasa')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('icd9cm_code')->label('ICD-9-CM')->fontFamily('mono')->placeholder('—')->searchable()->visibleFrom('md'),
                TextColumn::make('base_price')->label('Tarif dasar')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state, false)),
                TextColumn::make('duration_minutes')->label('Durasi')->suffix(' mnt')->alignEnd()->visibleFrom('lg'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Kategori')->options(TreatmentCategory::class),
                TernaryFilter::make('is_active')->label('Aktif'),
                TernaryFilter::make('icd9cm_code')->label('Punya kode ICD-9-CM')->nullable(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
