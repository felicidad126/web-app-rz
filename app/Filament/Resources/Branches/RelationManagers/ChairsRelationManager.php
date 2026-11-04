<?php

namespace App\Filament\Resources\Branches\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class ChairsRelationManager extends RelationManager
{
    protected static string $relationship = 'chairs';

    protected static ?string $title = 'Kursi / Dental Unit';

    protected static ?string $modelLabel = 'kursi';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama kursi')
                    ->placeholder('Mis. Unit 1')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('branch_id', $this->getOwnerRecord()->getKey())),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nama'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah kursi'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
