<?php

namespace App\Filament\Resources\TreatmentPlans;

use App\Filament\Resources\TreatmentPlans\Pages\CreateTreatmentPlan;
use App\Filament\Resources\TreatmentPlans\Pages\EditTreatmentPlan;
use App\Filament\Resources\TreatmentPlans\Pages\ListTreatmentPlans;
use App\Filament\Resources\TreatmentPlans\Schemas\TreatmentPlanForm;
use App\Filament\Resources\TreatmentPlans\Tables\TreatmentPlansTable;
use App\Filament\Support\NavGroup;
use App\Models\TreatmentPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TreatmentPlanResource extends Resource
{
    protected static ?string $model = TreatmentPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CLINICAL;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'rencana perawatan';

    protected static ?string $pluralModelLabel = 'rencana perawatan';

    protected static ?string $navigationLabel = 'Rencana perawatan';

    protected static ?string $slug = 'rencana-perawatan';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return TreatmentPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TreatmentPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTreatmentPlans::route('/'),
            'create' => CreateTreatmentPlan::route('/create'),
            'edit' => EditTreatmentPlan::route('/{record}/edit'),
        ];
    }
}
