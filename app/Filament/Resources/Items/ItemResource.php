<?php

namespace App\Filament\Resources\Items;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Items\RelationManagers\BatchesRelationManager;
use App\Filament\Resources\Items\RelationManagers\StockCardRelationManager;
use App\Filament\Resources\Items\RelationManagers\UnitsRelationManager;
use App\Filament\Resources\Items\Schemas\ItemForm;
use App\Filament\Resources\Items\Tables\ItemsTable;
use App\Filament\Support\NavGroup;
use App\Models\Item;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ItemResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::INVENTORY;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'obat / bahan';

    protected static ?string $pluralModelLabel = 'obat & bahan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UnitsRelationManager::class,
            BatchesRelationManager::class,
            StockCardRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
