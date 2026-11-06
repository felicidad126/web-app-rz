<?php

namespace App\Filament\Support;

use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Form & tabel bersama untuk master kode (ICD-10, ICD-9-CM). */
final class CodeListSchema
{
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama (sesuai klasifikasi)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_id')
                    ->label('Nama (Bahasa Indonesia)')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Kode')->fontFamily('mono')->searchable()->sortable(),
                // Nama Indonesia yang dibaca staf (jatuh ke nama klasifikasi bila belum diisi); nama asli hanya di layar lebar.
                TextColumn::make('name_id')->label('Nama')->searchable()->wrap()
                    ->state(fn (Model $record): ?string => $record->getAttribute('name_id') ?: $record->getAttribute('name')),
                TextColumn::make('name')->label('Nama klasifikasi')->searchable()->wrap()->visibleFrom('lg'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
