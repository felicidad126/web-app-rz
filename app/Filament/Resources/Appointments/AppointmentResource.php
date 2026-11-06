<?php

namespace App\Filament\Resources\Appointments;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Pages\EditAppointment;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use App\Filament\Support\NavGroup;
use App\Models\Appointment;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AppointmentResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::DAILY;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'janji temu';

    protected static ?string $pluralModelLabel = 'janji temu';

    protected static ?string $navigationLabel = 'Janji temu';

    protected static ?string $slug = 'janji-temu';

    public static function form(Schema $schema): Schema
    {
        return AppointmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppointmentsTable::configure($table);
    }

    /** Dokter (tanpa peran lain) hanya melihat janji temunya sendiri. */
    public static function getEloquentQuery(): Builder
    {
        return self::scopeToOwnPatientsForDoctor(parent::getEloquentQuery());
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeToOwnPatientsForDoctor(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user instanceof User && $user->hasRole('dokter') && ! $user->hasAnyRole(['owner', 'admin', 'perawat', 'resepsionis', config('filament-shield.super_admin.name')])) {
            $query->where('practitioner_id', $user->practitioner->id ?? 0);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppointments::route('/'),
            'create' => CreateAppointment::route('/create'),
            'edit' => EditAppointment::route('/{record}/edit'),
        ];
    }
}
