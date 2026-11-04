<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\BloodType;
use App\Enums\Gender;
use App\Filament\Support\NikField;
use App\Models\Patient;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Identitas')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('rm_number')
                            ->label('No. Rekam Medis')
                            ->placeholder('Dibuat otomatis saat disimpan')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('name')
                            ->label('Nama lengkap')
                            ->required()
                            ->maxLength(255),
                        NikField::make(Patient::class),
                        TextInput::make('birth_place')
                            ->label('Tempat lahir')
                            ->maxLength(255),
                        DatePicker::make('birth_date')
                            ->label('Tanggal lahir')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->required(),
                        ToggleButtons::make('gender')
                            ->label('Jenis kelamin')
                            ->options(Gender::class)
                            ->inline()
                            ->required(),
                        Select::make('blood_type')
                            ->label('Golongan darah')
                            ->options(BloodType::class),
                        TextInput::make('occupation')
                            ->label('Pekerjaan')
                            ->maxLength(255),
                    ]),
                Section::make('Kontak')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('phone')
                            ->label('No. HP')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Textarea::make('address')
                            ->label('Alamat')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Riwayat Medis Penting')
                    ->description('Ditampilkan sebagai peringatan untuk dokter.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Textarea::make('allergies')
                            ->label('Alergi')
                            ->placeholder('Mis. penisilin, lidokain, lateks')
                            ->rows(2),
                        Textarea::make('systemic_diseases')
                            ->label('Penyakit sistemik')
                            ->placeholder('Mis. hipertensi, diabetes, kelainan jantung')
                            ->rows(2),
                    ]),
                Section::make('Kontak Darurat')
                    ->columns(3)
                    ->columnSpanFull()
                    ->collapsible()
                    ->components([
                        TextInput::make('emergency_contact_name')
                            ->label('Nama')
                            ->maxLength(255),
                        TextInput::make('emergency_contact_phone')
                            ->label('No. HP')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('emergency_contact_relation')
                            ->label('Hubungan')
                            ->maxLength(255),
                    ]),
                Section::make('Administrasi')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('default_payer_id')
                            ->label('Penjamin default')
                            ->relationship('defaultPayer', 'name', fn ($query) => $query->where('is_active', true))
                            ->preload(),
                        Select::make('registered_branch_id')
                            ->label('Cabang registrasi')
                            ->relationship('registeredBranch', 'name', fn ($query) => $query->where('is_active', true))
                            ->default(fn (): ?int => auth()->user()?->branches()->value('branches.id'))
                            ->preload(),
                        TextInput::make('satusehat_ihs_id')
                            ->label('IHS Number (SATUSEHAT)')
                            ->helperText('Diisi otomatis setelah integrasi SATUSEHAT aktif.')
                            ->maxLength(255),
                        Checkbox::make('consent_data_at')
                            ->label('Pasien menyetujui pemrosesan data pribadi (UU PDP)')
                            ->formatStateUsing(fn ($state): bool => filled($state))
                            ->dehydrateStateUsing(fn ($state, ?Patient $record) => $state ? ($record->consent_data_at ?? now()) : null)
                            ->accepted(fn (string $operation): bool => $operation === 'create')
                            ->validationMessages(['accepted' => 'Persetujuan pasien wajib dicatat saat registrasi.']),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
