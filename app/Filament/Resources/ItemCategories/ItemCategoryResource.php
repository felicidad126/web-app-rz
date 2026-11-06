<?php

namespace App\Filament\Resources\ItemCategories;

use App\Filament\Resources\ItemCategories\Pages\ManageItemCategories;
use App\Filament\Support\NavGroup;
use App\Models\ItemCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ItemCategoryResource extends Resource
{
    protected static ?string $model = ItemCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::INVENTORY;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'kategori-barang';

    protected static ?string $modelLabel = 'kategori';

    protected static ?string $pluralModelLabel = 'kategori barang';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nama kategori')->required()->maxLength(80)->unique(ignoreRecord: true),
                TextInput::make('description')->label('Keterangan')->maxLength(255),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('items'))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('description')->label('Keterangan')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('items_count')->label('Jumlah barang')->numeric()->alignEnd(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageItemCategories::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
