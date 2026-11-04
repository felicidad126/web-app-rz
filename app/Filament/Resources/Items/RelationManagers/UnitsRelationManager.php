<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Models\Item;
use App\Models\ItemUnit;
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

class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'Konversi Satuan';

    protected static ?string $modelLabel = 'konversi satuan';

    public function form(Schema $schema): Schema
    {
        /** @var Item $item */
        $item = $this->getOwnerRecord()->loadMissing('baseUnit');

        return $schema
            ->components([
                Select::make('unit_id')
                    ->label('Satuan')
                    ->relationship('unit', 'name', fn ($query) => $query->whereKeyNot($item->base_unit_id))
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('item_id', $item->getKey()))
                    ->preload()
                    ->required(),
                TextInput::make('factor_to_base')
                    ->label('Isi per satuan')
                    ->helperText(fn (): string => 'Berapa '.$item->baseUnit->name.' dalam 1 satuan ini. Contoh: 1 box = 100.')
                    ->suffix($item->baseUnit->name)
                    ->numeric()
                    ->minValue(0.0001)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var Item $item */
        $item = $this->getOwnerRecord()->loadMissing('baseUnit');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('unit'))
            ->columns([
                TextColumn::make('unit.name')->label('Satuan'),
                TextColumn::make('factor_to_base')
                    ->label('Konversi')
                    ->formatStateUsing(fn (ItemUnit $record): string => '1 '.$record->unit->name.' = '.rtrim(rtrim(number_format((float) $record->factor_to_base, 4, ',', '.'), '0'), ',').' '.$item->baseUnit->name),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah konversi'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
