<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BranchForm
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
                            ->label('Kode cabang')
                            ->helperText('Singkat, huruf kapital. Dipakai di nomor invoice. Contoh: JKT1')
                            ->required()
                            ->maxLength(10)
                            ->alphaNum()
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null),
                        TextInput::make('name')
                            ->label('Nama cabang')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telepon')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('satusehat_location_id')
                            ->label('ID lokasi SATUSEHAT')
                            ->helperText('Dari SATUSEHAT Platform, atau klik "Daftarkan ke SATUSEHAT" di atas.')
                            ->maxLength(255),
                        Textarea::make('address')
                            ->label('Alamat')
                            ->rows(2)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }
}
