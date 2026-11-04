<?php

namespace App\Filament\Resources\Treatments\Tables;

use App\Enums\TreatmentCategory;
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
                TextColumn::make('code')->label('Kode')->weight('bold')->searchable()->sortable(),
                TextColumn::make('name')->label('Tindakan')->searchable()->sortable()->wrap(),
                TextColumn::make('category')->label('Kategori')->badge()->visibleFrom('md'),
                TextColumn::make('icd9cm_code')->label('ICD-9-CM')->placeholder('-')->searchable()->visibleFrom('md'),
                TextColumn::make('base_price')->label('Tarif dasar')->money('IDR', locale: 'id')->sortable(),
                TextColumn::make('duration_minutes')->label('Durasi')->suffix(' mnt')->visibleFrom('lg'),
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
