<?php

namespace App\Filament\Resources\Treatments\Schemas;

use App\Enums\FeeGroup;
use App\Enums\TreatmentCategory;
use App\Models\Icd9cmCode;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TreatmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('code')
                            ->label('Kode internal')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->label('Nama tindakan')
                            ->required()
                            ->maxLength(255),
                        Select::make('category')
                            ->label('Kategori')
                            ->options(TreatmentCategory::class)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, string $operation): void {
                                if ($operation === 'create' && $state !== null) {
                                    $set('fee_group', FeeGroup::fromCategory($state instanceof TreatmentCategory ? $state : TreatmentCategory::from($state)));
                                }
                            })
                            ->required(),
                        Select::make('fee_group')
                            ->label('Jenis jasa')
                            ->helperText('Kelompok bagi hasil dokter (menentukan aturan jasa yang berlaku).')
                            ->options(FeeGroup::class)
                            ->default(FeeGroup::General)
                            ->required(),
                        Select::make('icd9cm_code')
                            ->label('Kode ICD-9-CM')
                            ->helperText('Wajib untuk pengiriman tindakan ke SATUSEHAT.')
                            ->relationship('icd9cm', 'code', fn ($query) => $query->where('is_active', true))
                            ->getOptionLabelFromRecordUsing(fn (Icd9cmCode $record): string => $record->label)
                            ->searchable(['code', 'name', 'name_id'])
                            ->preload(),
                        TextInput::make('base_price')
                            ->label('Tarif dasar')
                            ->helperText('Bisa di-override per cabang setelah disimpan.')
                            ->prefix('Rp')
                            ->numeric()
                            ->minValue(0)
                            ->step(1000)
                            ->required(),
                        TextInput::make('duration_minutes')
                            ->label('Durasi default')
                            ->suffix('menit')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(600)
                            ->default(30)
                            ->required(),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(2)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }
}
