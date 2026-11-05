<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Enums\AppointmentStatus;
use App\Enums\TreatmentCategory;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Resources\Appointments\AppointmentActions;
use App\Models\Appointment;
use App\Support\BranchContext;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('start_at')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['patient', 'practitioner.user', 'chair', 'branch']))
            ->searchPlaceholder('Cari nama / No. RM pasien')
            ->columns([
                TextColumn::make('start_at')
                    ->label('Waktu')
                    ->formatStateUsing(fn (Appointment $record): string => $record->start_at->format('d/m H:i').'–'.$record->end_at->format('H:i'))
                    ->description(fn (Appointment $record): string => $record->start_at->translatedFormat('l'))
                    ->sortable(),
                TextColumn::make('patient.name')
                    ->label('Pasien')
                    ->description(fn (Appointment $record): string => $record->patient->rm_number)
                    ->searchable(['name', 'rm_number']),
                TextColumn::make('practitioner.user.name')->label('Dokter'),
                TextColumn::make('chair.name')->label('Kursi')->placeholder('—'),
                TextColumn::make('category')->label('Jenis')->badge()->color('gray')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('reason')->label('Rencana')->limit(30)->placeholder('—')->visibleFrom('xl'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('branch.name')->label('Cabang')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('date')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y')->default(today()),
                        DatePicker::make('until')->label('Sampai')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('start_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('start_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): ?string => ($data['from'] ?? null) ? 'Mulai '.date('d/m/Y', strtotime($data['from'])) : null),
                SelectFilter::make('status')->label('Status')->options(AppointmentStatus::class)->multiple(),
                SelectFilter::make('practitioner_id')->label('Dokter')->options(fn (): array => PractitionerOptions::dentists()),
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->options(fn (): array => BranchContext::options())
                    ->default(fn (): ?int => BranchContext::id()),
                SelectFilter::make('category')->label('Jenis')->options(TreatmentCategory::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ...AppointmentActions::statusActions(),
                    EditAction::make(),
                ]),
            ]);
    }
}
