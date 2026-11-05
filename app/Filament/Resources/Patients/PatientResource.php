<?php

namespace App\Filament\Resources\Patients;

use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\PatientOdontogram;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\DepositRelationManager;
use App\Filament\Resources\Patients\RelationManagers\PatientPayersRelationManager;
use App\Filament\Resources\Patients\RelationManagers\RecallsRelationManager;
use App\Filament\Resources\Patients\RelationManagers\TreatmentPlansRelationManager;
use App\Filament\Resources\Patients\RelationManagers\VisitsRelationManager;
use App\Filament\Resources\Patients\Schemas\PatientForm;
use App\Filament\Resources\Patients\Schemas\PatientInfolist;
use App\Filament\Resources\Patients\Tables\PatientsTable;
use App\Filament\Support\NavGroup;
use App\Models\Patient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::PATIENTS;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'pasien';

    protected static ?string $pluralModelLabel = 'pasien';

    protected static ?string $navigationLabel = 'Data Pasien';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PatientForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PatientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PatientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PatientPayersRelationManager::class,
            VisitsRelationManager::class,
            TreatmentPlansRelationManager::class,
            RecallsRelationManager::class,
            AttachmentsRelationManager::class,
            DepositRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatients::route('/'),
            'create' => CreatePatient::route('/create'),
            'view' => ViewPatient::route('/{record}'),
            'edit' => EditPatient::route('/{record}/edit'),
            'odontogram' => PatientOdontogram::route('/{record}/odontogram'),
        ];
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['rm_number', 'name', 'phone'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Patient $record */
        return [
            'No. RM' => $record->rm_number,
            'Tgl lahir' => $record->birth_date->format('d/m/Y'),
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
