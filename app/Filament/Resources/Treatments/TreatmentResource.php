<?php

namespace App\Filament\Resources\Treatments;

use App\Filament\Resources\Treatments\Pages\CreateTreatment;
use App\Filament\Resources\Treatments\Pages\EditTreatment;
use App\Filament\Resources\Treatments\Pages\ListTreatments;
use App\Filament\Resources\Treatments\RelationManagers\BranchPricesRelationManager;
use App\Filament\Resources\Treatments\RelationManagers\MaterialsRelationManager;
use App\Filament\Resources\Treatments\Schemas\TreatmentForm;
use App\Filament\Resources\Treatments\Tables\TreatmentsTable;
use App\Filament\Support\NavGroup;
use App\Models\Treatment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TreatmentResource extends Resource
{
    protected static ?string $model = Treatment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CLINICAL_MASTER;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'tindakan';

    protected static ?string $pluralModelLabel = 'tindakan & tarif';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TreatmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TreatmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BranchPricesRelationManager::class,
            MaterialsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTreatments::route('/'),
            'create' => CreateTreatment::route('/create'),
            'edit' => EditTreatment::route('/{record}/edit'),
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
