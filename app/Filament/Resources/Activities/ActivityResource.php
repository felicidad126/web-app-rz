<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Support\NavGroup;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/** Audit log (read-only): perubahan data dan akses data pasien. */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::SETTINGS;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $modelLabel = 'aktivitas';

    protected static ?string $pluralModelLabel = 'audit log';

    protected static ?string $slug = 'audit-log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i:s'),
                TextEntry::make('causer.name')->label('Oleh')->placeholder('Sistem'),
                TextEntry::make('event')->label('Event')->badge(),
                TextEntry::make('description')->label('Keterangan'),
                TextEntry::make('subject_type')->label('Data')->formatStateUsing(fn (?string $state): ?string => $state ? class_basename($state) : null),
                TextEntry::make('subject_id')->label('ID data'),
                KeyValueEntry::make('properties.attributes')->label('Nilai baru')->columnSpanFull(),
                KeyValueEntry::make('properties.old')->label('Nilai lama')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('causer.name')->label('Oleh')->placeholder('Sistem')->searchable(),
                TextColumn::make('log_name')->label('Log')->badge()->color(fn (?string $state): string => $state === 'access' ? 'warning' : 'gray'),
                TextColumn::make('event')->label('Event')->badge(),
                TextColumn::make('subject_type')->label('Data')->formatStateUsing(fn (?string $state): ?string => $state ? class_basename($state) : null)->visibleFrom('md'),
                TextColumn::make('subject_id')->label('ID')->visibleFrom('md'),
                TextColumn::make('description')->label('Keterangan')->limit(40)->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Log')->options(['audit' => 'Perubahan data', 'access' => 'Akses data pasien']),
                SelectFilter::make('event')->label('Event')->options([
                    'created' => 'created', 'updated' => 'updated', 'deleted' => 'deleted', 'restored' => 'restored', 'viewed' => 'viewed', 'mfa_reset' => 'mfa_reset',
                ]),
                SelectFilter::make('causer_id')
                    ->label('Pengguna')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $id): Builder => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
