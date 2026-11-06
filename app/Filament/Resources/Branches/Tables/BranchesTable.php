<?php

namespace App\Filament\Resources\Branches\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BranchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['chairs', 'users']))
            ->columns([
                TextColumn::make('code')->label('Kode')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('phone')->label('Telepon')->placeholder('-')->visibleFrom('md'),
                TextColumn::make('chairs_count')->label('Kursi')->numeric()->alignEnd(),
                TextColumn::make('users_count')->label('Staf')->numeric()->alignEnd()->visibleFrom('md'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
            ]);
    }
}
