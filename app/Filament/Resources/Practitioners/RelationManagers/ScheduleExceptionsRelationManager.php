<?php

namespace App\Filament\Resources\Practitioners\RelationManagers;

use App\Models\ScheduleException;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScheduleExceptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'scheduleExceptions';

    protected static ?string $title = 'Cuti & libur';

    protected static ?string $modelLabel = 'cuti/libur';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required(),
                TimePicker::make('start_time')
                    ->label('Dari jam')
                    ->seconds(false)
                    ->helperText('Kosongkan untuk seharian.'),
                TimePicker::make('end_time')
                    ->label('Sampai jam')
                    ->seconds(false)
                    ->requiredWith('start_time')
                    ->after('start_time'),
                TextInput::make('reason')
                    ->label('Alasan')
                    ->placeholder('Mis. cuti tahunan, seminar')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Tanggal')->date('D, d/m/Y')->sortable(),
                TextColumn::make('start_time')
                    ->label('Jam')
                    ->formatStateUsing(fn (ScheduleException $record): string => $record->isWholeDay()
                        ? 'Seharian'
                        : substr((string) $record->start_time, 0, 5).'–'.substr((string) $record->end_time, 0, 5))
                    ->placeholder('Seharian'),
                TextColumn::make('reason')->label('Alasan'),
            ])
            ->filters([
                Filter::make('upcoming')
                    ->label('Yang akan datang')
                    ->default()
                    ->query(fn (Builder $query) => $query->whereDate('date', '>=', today())),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah cuti/libur'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
