<?php

namespace App\Filament\Resources\Icd10Codes;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Icd10Codes\Pages\ManageIcd10Codes;
use App\Filament\Support\CodeListSchema;
use App\Filament\Support\NavGroup;
use App\Models\Icd10Code;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class Icd10CodeResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Icd10Code::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CLINICAL_MASTER;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'ICD-10 (Diagnosa)';

    protected static ?string $modelLabel = 'kode ICD-10';

    protected static ?string $pluralModelLabel = 'kode ICD-10';

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
            'index' => ManageIcd10Codes::route('/'),
        ];
    }
}
