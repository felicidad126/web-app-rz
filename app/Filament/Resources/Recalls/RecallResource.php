<?php

namespace App\Filament\Resources\Recalls;

use App\Enums\RecallStatus;
use App\Filament\Clinic\PatientSelect;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Recalls\Pages\ManageRecalls;
use App\Filament\Support\NavGroup;
use App\Models\Recall;
use App\Models\Treatment;
use App\Support\BranchContext;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RecallResource extends Resource
{
    protected static ?string $model = Recall::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::DAILY;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'recall';

    protected static ?string $pluralModelLabel = 'recall';

    protected static ?string $navigationLabel = 'Recall (kontrol)';

    protected static ?string $slug = 'recall';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                PatientSelect::make()->columnSpanFull(),
                TextInput::make('reason')->label('Keperluan')->placeholder('Mis. scaling 6 bulan, kontrol ortho')->required()->maxLength(255),
                DatePicker::make('due_date')->label('Jatuh tempo')->native(false)->displayFormat('d/m/Y')->required(),
                Select::make('treatment_id')->label('Tindakan terkait')->options(fn (): array => Treatment::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                Select::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())->default(fn (): ?int => BranchContext::id()),
                Select::make('status')->label('Status')->options(RecallStatus::class)->default(RecallStatus::Pending)->required(),
                TextInput::make('notes')->label('Catatan')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['patient', 'branch']))
            ->columns([
                TextColumn::make('due_date')
                    ->label('Jatuh tempo')
                    ->date('d/m/Y')
                    ->color(fn (Recall $record): ?string => $record->isOverdue() ? 'warning' : null)
                    ->description(fn (Recall $record): ?string => $record->isOverdue() ? 'lewat '.$record->due_date->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE) : null)
                    ->sortable(),
                TextColumn::make('patient.name')
                    ->label('Pasien')
                    ->description(fn (Recall $record): string => trim($record->patient->rm_number.' · '.$record->patient->phone, ' ·'))
                    ->searchable(['name', 'rm_number', 'phone']),
                TextColumn::make('reason')->label('Keperluan'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('last_contacted_at')->label('Terakhir dihubungi')->dateTime('d/m H:i')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('notes')->label('Catatan')->placeholder('—')->limit(30)->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(RecallStatus::class)->multiple()
                    ->default([RecallStatus::Pending->value, RecallStatus::Contacted->value]),
                Filter::make('due_soon')
                    ->label('Jatuh tempo ≤ 30 hari (termasuk yang lewat)')
                    ->default()
                    ->query(fn (Builder $query) => $query->whereDate('due_date', '<=', today()->addDays(30))),
                SelectFilter::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options()),
            ])
            ->recordActions([
                Action::make('contacted')
                    ->label('Dihubungi')
                    ->icon(Heroicon::OutlinedPhone)
                    ->color('info')
                    ->schema([TextInput::make('notes')->label('Hasil')->placeholder('Mis. minta dihubungi minggu depan')->maxLength(255)])
                    ->visible(fn (Recall $record): bool => in_array($record->status, [RecallStatus::Pending, RecallStatus::Contacted], true))
                    ->authorize(fn (Recall $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(fn (Recall $record, array $data) => $record->update([
                        'status' => RecallStatus::Contacted,
                        'last_contacted_at' => now(),
                        'notes' => $data['notes'] ?? $record->notes,
                    ])),
                ActionGroup::make([
                    Action::make('book')
                        ->label('Buat janji')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->visible(fn (Recall $record): bool => (auth()->user()?->can('Create:Appointment') ?? false) && $record->status !== RecallStatus::Done)
                        ->url(fn (Recall $record): string => AppointmentResource::getUrl('create', ['patient' => $record->patient_id])),
                    Action::make('booked')
                        ->label('Tandai sudah buat janji')
                        ->icon(Heroicon::OutlinedCheck)
                        ->visible(fn (Recall $record): bool => in_array($record->status, [RecallStatus::Pending, RecallStatus::Contacted], true))
                        ->authorize(fn (Recall $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->action(fn (Recall $record) => $record->update(['status' => RecallStatus::Booked])),
                    Action::make('cancel')
                        ->label('Batalkan recall')
                        ->icon(Heroicon::OutlinedXMark)
                        ->requiresConfirmation()
                        ->visible(fn (Recall $record): bool => $record->status !== RecallStatus::Cancelled)
                        ->authorize(fn (Recall $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->action(fn (Recall $record) => $record->update(['status' => RecallStatus::Cancelled])),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRecalls::route('/')];
    }
}
