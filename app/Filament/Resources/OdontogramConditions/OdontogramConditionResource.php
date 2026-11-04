<?php

namespace App\Filament\Resources\OdontogramConditions;

use App\Enums\OdontogramCategory;
use App\Enums\OdontogramScope;
use App\Filament\Resources\OdontogramConditions\Pages\ManageOdontogramConditions;
use App\Filament\Support\NavGroup;
use App\Models\OdontogramCondition;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class OdontogramConditionResource extends Resource
{
    protected static ?string $model = OdontogramCondition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CLINICAL_MASTER;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Kondisi Odontogram';

    protected static ?string $modelLabel = 'kondisi odontogram';

    protected static ?string $pluralModelLabel = 'kondisi odontogram';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode')
                    ->helperText('Singkatan standar, huruf kecil. Contoh: car, amf, mis.')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtolower($state) : null),
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                Select::make('category')
                    ->label('Kategori')
                    ->options(OdontogramCategory::class)
                    ->required(),
                Select::make('applies_to')
                    ->label('Dicatat')
                    ->options(OdontogramScope::class)
                    ->required(),
                ColorPicker::make('color')
                    ->label('Warna')
                    ->default('#6b7280'),
                TextInput::make('symbol')
                    ->label('Simbol')
                    ->maxLength(20),
                TextInput::make('sort')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Textarea::make('description')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('code')->label('Kode')->weight('bold')->searchable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('category')->label('Kategori')->badge(),
                TextColumn::make('applies_to')->label('Dicatat')->badge()->color('gray')->visibleFrom('md'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Kategori')->options(OdontogramCategory::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOdontogramConditions::route('/'),
        ];
    }
}
