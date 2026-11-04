<?php

namespace App\Filament\Resources\Units;

use App\Filament\Resources\Units\Pages\ManageUnits;
use App\Filament\Support\NavGroup;
use App\Models\Unit;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::INVENTORY;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'satuan';

    protected static ?string $pluralModelLabel = 'satuan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama satuan')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                // Satuan yang sudah dipakai barang tidak bisa dihapus (FK restrict).
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUnits::route('/'),
        ];
    }
}
