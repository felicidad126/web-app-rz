<?php

namespace App\Filament\Resources\Practitioners\Schemas;

use App\Enums\PractitionerType;
use App\Filament\Support\NikField;
use App\Models\Practitioner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PractitionerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profil')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('user_id')
                            ->label('Akun pengguna')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->unique(ignoreRecord: true)
                            ->required(),
                        Select::make('type')
                            ->label('Jenis')
                            ->options(PractitionerType::class)
                            ->required(),
                        TextInput::make('specialty')
                            ->label('Spesialisasi')
                            ->placeholder('Mis. Sp.Ort, Sp.KGA')
                            ->maxLength(255),
                        NikField::make(Practitioner::class),
                    ]),
                Section::make('Izin Praktik')
                    ->columns(3)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('str_number')
                            ->label('No. STR')
                            ->maxLength(255),
                        TextInput::make('sip_number')
                            ->label('No. SIP')
                            ->maxLength(255),
                        DatePicker::make('sip_expired_at')
                            ->label('SIP berlaku sampai')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->helperText('Sistem akan memberi peringatan '.config('clinic.sip_expiry_warning_days').' hari sebelum kedaluwarsa.'),
                    ]),
                Section::make('SATUSEHAT')
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('satusehat_ihs_id')
                            ->label('IHS Practitioner ID')
                            ->helperText('Diisi otomatis dari NIK setelah integrasi SATUSEHAT aktif (Fase 4).')
                            ->maxLength(255),
                    ]),
            ]);
    }
}
