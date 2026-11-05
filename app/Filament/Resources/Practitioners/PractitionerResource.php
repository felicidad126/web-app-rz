<?php

namespace App\Filament\Resources\Practitioners;

use App\Filament\Resources\Practitioners\Pages\CreatePractitioner;
use App\Filament\Resources\Practitioners\Pages\EditPractitioner;
use App\Filament\Resources\Practitioners\Pages\ListPractitioners;
use App\Filament\Resources\Practitioners\RelationManagers\ScheduleExceptionsRelationManager;
use App\Filament\Resources\Practitioners\RelationManagers\SchedulesRelationManager;
use App\Filament\Resources\Practitioners\Schemas\PractitionerForm;
use App\Filament\Resources\Practitioners\Tables\PractitionersTable;
use App\Filament\Support\NavGroup;
use App\Models\Practitioner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PractitionerResource extends Resource
{
    protected static ?string $model = Practitioner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::ORGANIZATION;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'tenaga kesehatan';

    protected static ?string $pluralModelLabel = 'tenaga kesehatan';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        /** @var Practitioner|null $record */
        return $record?->user->name ?? static::getModelLabel();
    }

    /** Judul diambil dari relasi user, bukan kolom, jadi aktifkan secara eksplisit. */
    public static function hasRecordTitle(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return PractitionerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PractitionersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SchedulesRelationManager::class,
            ScheduleExceptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPractitioners::route('/'),
            'create' => CreatePractitioner::route('/create'),
            'edit' => EditPractitioner::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->with('user')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
