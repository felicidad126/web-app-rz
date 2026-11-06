<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Enums\Gender;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Patient;
use App\Support\BlindIndex;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Cari RM, nama, HP, NIK')
            ->columns([
                TextColumn::make('rm_number')
                    ->label('No. RM')
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Medium)
                    ->copyable()
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $query->where('rm_number', 'like', "%{$search}%");

                        // Pencarian NIK lewat blind index (harus 16 digit persis).
                        if (strlen(BlindIndex::normalize($search) ?? '') === 16) {
                            $query->orWhere('nik_hash', BlindIndex::hash($search));
                        }

                        return $query;
                    }),
                TextColumn::make('name')
                    ->label('Nama')
                    ->description(fn (Patient $record): string => $record->gender->getLabel().' · '.$record->age.' th')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('birth_date')
                    ->label('Tgl lahir')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('phone')
                    ->label('No. HP')
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('defaultPayer.name')
                    ->label('Penjamin')
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('lg'),
                TextColumn::make('registeredBranch.name')
                    ->label('Cabang')
                    ->toggleable()
                    ->visibleFrom('xl'),
                TextColumn::make('allergies')
                    ->label('Alergi')
                    ->color('danger')
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gender')->label('Jenis kelamin')->options(Gender::class),
                SelectFilter::make('registered_branch_id')->label('Cabang')->relationship('registeredBranch', 'name'),
                SelectFilter::make('default_payer_id')->label('Penjamin')->relationship('defaultPayer', 'name'),
                TrashedFilter::make(),
            ])
            // Klik baris membuka data pasien; tombol "Lihat" tidak perlu, "Ubah" cukup ikon agar muat di HP.
            ->recordUrl(fn (Patient $record): ?string => PatientResource::canView($record) ? PatientResource::getUrl('view', ['record' => $record]) : null)
            ->recordActions([
                EditAction::make()->hiddenLabel()->tooltip('Ubah'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
