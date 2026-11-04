<?php

namespace App\Filament\Resources\Treatments\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class BranchPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'branchPrices';

    protected static ?string $title = 'Tarif per Cabang';

    protected static ?string $modelLabel = 'tarif cabang';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('treatment_id', $this->getOwnerRecord()->getKey()))
                    ->preload()
                    ->required(),
                TextInput::make('price')
                    ->label('Tarif')
                    ->prefix('Rp')
                    ->numeric()
                    ->minValue(0)
                    ->step(1000)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Kosongkan jika cabang memakai tarif dasar.')
            ->columns([
                TextColumn::make('branch.name')->label('Cabang'),
                TextColumn::make('price')->label('Tarif')->money('IDR', locale: 'id'),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah tarif cabang'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
