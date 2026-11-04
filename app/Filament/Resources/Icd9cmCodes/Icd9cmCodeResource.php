<?php

namespace App\Filament\Resources\Icd9cmCodes;

use App\Filament\Resources\Icd9cmCodes\Pages\ManageIcd9cmCodes;
use App\Filament\Support\CodeListSchema;
use App\Filament\Support\NavGroup;
use App\Models\Icd9cmCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class Icd9cmCodeResource extends Resource
{
    protected static ?string $model = Icd9cmCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CLINICAL_MASTER;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'ICD-9-CM (Tindakan)';

    protected static ?string $modelLabel = 'kode ICD-9-CM';

    protected static ?string $pluralModelLabel = 'kode ICD-9-CM';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return CodeListSchema::form($schema);
    }

    public static function table(Table $table): Table
    {
        return CodeListSchema::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIcd9cmCodes::route('/'),
        ];
    }
}
