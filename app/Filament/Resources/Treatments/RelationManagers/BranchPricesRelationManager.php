<?php

namespace App\Filament\Resources\Treatments\RelationManagers;

use App\Filament\Support\MoneyInput;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class BranchPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'branchPrices';

    protected static ?string $title = 'Tarif per cabang';

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
                MoneyInput::make('price')
                    ->label('Tarif')
                    ->minValue(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Kosongkan jika cabang memakai tarif dasar.')
            ->columns([
                TextColumn::make('branch.name')->label('Cabang'),
                TextColumn::make('price')->label('Tarif')->alignEnd()
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state, false)),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah tarif cabang')->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
