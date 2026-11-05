<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Filament\Resources\Visits\VisitResource;
use App\Models\Visit;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    protected static ?string $title = 'Riwayat kunjungan';

    protected static ?string $modelLabel = 'kunjungan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('ViewAny:Visit') ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $canExamine = auth()->user()?->can('View:MedicalRecord') ?? false;

        return $table
            ->defaultSort('visit_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['practitioner.user', 'branch', 'medicalRecord.diagnoses.icd10', 'procedures.treatment']))
            ->recordUrl(fn (Visit $record): ?string => $canExamine ? VisitResource::getUrl('examine', ['record' => $record]) : null)
            ->columns([
                TextColumn::make('visit_date')->label('Tanggal')->date('d/m/Y')->description(fn (Visit $record): string => $record->visit_number),
                TextColumn::make('diagnoses')
                    ->label('Diagnosis')
                    ->state(fn (Visit $record): string => $canExamine
                        ? ($record->medicalRecord?->diagnoses->map(fn ($d) => $d->icd10_code.($d->tooth_number ? ' ('.$d->tooth_number.')' : ''))->implode(', ') ?: '—')
                        : '—')
                    ->wrap(),
                TextColumn::make('procedures')
                    ->label('Tindakan')
                    ->state(fn (Visit $record): string => $record->procedures->map(fn ($p) => $p->treatment->name.($p->tooth_number ? ' '.$p->tooth_number.($p->surfaces ? ' '.$p->surfaces : '') : ''))->implode(', ') ?: '—')
                    ->wrap(),
                TextColumn::make('practitioner.user.name')->label('Dokter'),
                TextColumn::make('branch.name')->label('Cabang')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Status')->badge(),
            ]);
    }
}
