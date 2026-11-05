<?php

namespace App\Filament\Resources\Visits;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Visits\Pages\ExamineVisit;
use App\Filament\Resources\Visits\Pages\ListVisits;
use App\Filament\Resources\Visits\RelationManagers\AddendaRelationManager;
use App\Filament\Resources\Visits\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Visits\RelationManagers\InformedConsentsRelationManager;
use App\Filament\Resources\Visits\RelationManagers\OdontogramEntriesRelationManager;
use App\Filament\Resources\Visits\Tables\VisitsTable;
use App\Filament\Support\NavGroup;
use App\Models\Visit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::DAILY;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'kunjungan';

    protected static ?string $pluralModelLabel = 'kunjungan';

    protected static ?string $navigationLabel = 'Kunjungan';

    protected static ?string $slug = 'kunjungan';

    protected static ?string $recordTitleAttribute = 'visit_number';

    public static function table(Table $table): Table
    {
        return VisitsTable::configure($table);
    }

    /** Daftar kunjungan dokter dibatasi pasiennya sendiri (matriks: jadwal & antrean "miliknya"). */
    public static function getEloquentQuery(): Builder
    {
        return AppointmentResource::scopeToOwnPatientsForDoctor(parent::getEloquentQuery());
    }

    /** Rekam medis tetap bisa dibuka dokter lain (mis. menggantikan rekan); setiap akses dicatat. */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getRelations(): array
    {
        return [
            OdontogramEntriesRelationManager::class,
            AttachmentsRelationManager::class,
            InformedConsentsRelationManager::class,
            AddendaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisits::route('/'),
            'examine' => ExamineVisit::route('/{record}/pemeriksaan'),
        ];
    }
}
