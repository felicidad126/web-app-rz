<?php

namespace App\Filament\Resources\Practitioners\RelationManagers;

use App\Models\Chair;
use App\Models\PractitionerSchedule;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';

    protected static ?string $title = 'Jadwal praktik';

    protected static ?string $modelLabel = 'jadwal';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('day_of_week')
                    ->label('Hari')
                    ->options(PractitionerSchedule::DAYS)
                    ->required(),
                Select::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->live()
                    ->required(),
                TimePicker::make('start_time')
                    ->label('Mulai')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->label('Selesai')
                    ->seconds(false)
                    ->after('start_time')
                    ->required(),
                Select::make('chair_id')
                    ->label('Kursi (opsional)')
                    ->options(fn (Get $get): array => Chair::query()
                        ->where('branch_id', $get('branch_id'))
                        ->where('is_active', true)
                        ->pluck('name', 'id')
                        ->all())
                    ->placeholder('Bebas'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('day_of_week')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['branch', 'chair']))
            ->columns([
                TextColumn::make('day_of_week')
                    ->label('Hari')
                    ->formatStateUsing(fn (int $state): string => PractitionerSchedule::DAYS[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Jam')
                    ->formatStateUsing(fn (PractitionerSchedule $record): string => substr($record->start_time, 0, 5).'–'.substr($record->end_time, 0, 5)),
                TextColumn::make('branch.name')->label('Cabang'),
                TextColumn::make('chair.name')->label('Kursi')->placeholder('Bebas'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah jadwal'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
