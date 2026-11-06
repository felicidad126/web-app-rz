<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Models\Patient;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;

class PatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Identitas')
                    ->columns(['default' => 2, 'lg' => 3])
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('rm_number')->label('No. RM')->copyable()->fontFamily(FontFamily::Mono)->weight(FontWeight::Medium),
                        TextEntry::make('name')->label('Nama'),
                        TextEntry::make('nik')
                            ->label('NIK')
                            ->formatStateUsing(fn (Patient $record): ?string => $record->maskedNik())
                            ->placeholder('-'),
                        TextEntry::make('birth_date')
                            ->label('Tempat, tanggal lahir')
                            ->formatStateUsing(fn (Patient $record): string => trim(($record->birth_place ? $record->birth_place.', ' : '').$record->birth_date->translatedFormat('d F Y').' ('.$record->age.' th)')),
                        TextEntry::make('gender')->label('Jenis kelamin'),
                        TextEntry::make('blood_type')->label('Gol. darah')->placeholder('-'),
                        TextEntry::make('occupation')->label('Pekerjaan')->placeholder('-'),
                        TextEntry::make('satusehat_ihs_id')->label('No. IHS SATUSEHAT')->fontFamily(FontFamily::Mono)->placeholder('Belum terhubung'),
                    ]),
                Section::make('Kontak')
                    ->columns(['default' => 2, 'lg' => 3])
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('phone')->label('No. HP')->placeholder('-'),
                        TextEntry::make('email')->label('Email')->placeholder('-'),
                        TextEntry::make('address')->label('Alamat')->placeholder('-'),
                        TextEntry::make('emergency_contact_name')
                            ->label('Kontak darurat')
                            ->formatStateUsing(fn (Patient $record): string => collect([$record->emergency_contact_name, $record->emergency_contact_relation ? "({$record->emergency_contact_relation})" : null, $record->emergency_contact_phone])->filter()->join(' '))
                            ->placeholder('-'),
                    ]),
                Section::make('Administrasi')
                    ->columns(['default' => 2, 'lg' => 3])
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('defaultPayer.name')->label('Penjamin default')->placeholder('-'),
                        TextEntry::make('registeredBranch.name')->label('Cabang registrasi')->placeholder('-'),
                        TextEntry::make('consent_data_at')->label('Persetujuan data')->dateTime('d/m/Y H:i')->placeholder('Belum ada'),
                        TextEntry::make('creator.name')->label('Didaftarkan oleh')->placeholder('-'),
                        TextEntry::make('created_at')->label('Terdaftar')->dateTime('d/m/Y H:i'),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-'),
                    ]),
            ]);
    }
}
