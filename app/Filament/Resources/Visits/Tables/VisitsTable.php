<?php

namespace App\Filament\Resources\Visits\Tables;

use App\Enums\VisitStatus;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Resources\Visits\VisitActions;
use App\Filament\Resources\Visits\VisitResource;
use App\Models\Visit;
use App\Support\BranchContext;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VisitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('queue_number')
            ->poll('30s')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['patient', 'practitioner.user', 'chair', 'medicalRecord']))
            ->recordUrl(fn (Visit $record): ?string => auth()->user()?->can('View:MedicalRecord') && $record->status !== VisitStatus::Cancelled
                ? VisitResource::getUrl('examine', ['record' => $record])
                : null)
            ->searchPlaceholder('Cari nama / No. RM / No. kunjungan')
            ->columns([
                TextColumn::make('queue_number')->label('#')->sortable(),
                TextColumn::make('patient.name')
                    ->label('Pasien')
                    ->description(fn (Visit $record): string => $record->patient->rm_number.' · '.$record->visit_number)
                    ->searchable(['name', 'rm_number']),
                TextColumn::make('practitioner.user.name')->label('Dokter'),
                TextColumn::make('chair.name')->label('Kursi')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('arrived_at')
                    ->label('Datang')
                    ->time('H:i')
                    ->description(fn (Visit $record): ?string => $record->status === VisitStatus::Waiting ? 'tunggu '.$record->waitingMinutes().' mnt' : null),
                IconColumn::make('is_walk_in')->label('Datang langsung')->boolean()->visibleFrom('lg'),
                TextColumn::make('medicalRecord.signed_at')->label('RM ditandatangani')->dateTime('H:i')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('visit_date')->label('Tanggal')->date('d/m/Y')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('visit_date')
                    ->label('Tanggal')
                    ->schema([DatePicker::make('date')->label('Tanggal')->native(false)->displayFormat('d/m/Y')->default(today())])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['date'] ?? null, fn (Builder $q, $date) => $q->whereDate('visit_date', $date)))
                    ->indicateUsing(fn (array $data): ?string => ($data['date'] ?? null) ? 'Tanggal '.date('d/m/Y', strtotime($data['date'])) : null),
                SelectFilter::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())->default(fn (): ?int => BranchContext::id()),
                SelectFilter::make('status')->label('Status')->options(VisitStatus::class)->multiple(),
                SelectFilter::make('practitioner_id')->label('Dokter')->options(fn (): array => PractitionerOptions::dentists()),
            ])
            ->recordActions([
                ActionGroup::make([
                    VisitActions::start(),
                    VisitActions::examine(),
                    VisitActions::cancel(),
                ]),
            ]);
    }
}
