<?php

namespace App\Filament\Resources\Practitioners\Schemas;

use App\Enums\PractitionerType;
use App\Filament\Support\MoneyInput;
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
                Section::make('Uang hadir & on call')
                    ->description('Dipakai saat mencatat kehadiran dokter; masuk ke rekap jasa bulanan.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (): bool => auth()->user()?->can('Manage:Commission') ?? false)
                    ->components([
                        MoneyInput::make('attendance_fee')->label('Uang hadir per hari')->minValue(0)->default(0),
                        MoneyInput::make('on_call_fee')->label('Uang on call per hari')->minValue(0)->default(0),
                    ]),
                Section::make('SATUSEHAT')
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('satusehat_ihs_id')
                            ->label('ID IHS tenaga kesehatan')
                            ->helperText('Dicari otomatis dari NIK saat kunjungan dikirim, atau klik "Cari IHS SATUSEHAT" di atas.')
                            ->maxLength(255),
                    ]),
            ]);
    }
}
