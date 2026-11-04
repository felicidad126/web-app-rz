<?php

namespace App\Filament\Imports;

use App\Enums\Gender;
use App\Filament\Imports\Concerns\IndonesianImportNotification;
use App\Models\Patient;
use Carbon\Carbon;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Throwable;

/**
 * Import pasien dari sistem lama (CSV).
 *
 * Jika kolom no_rm diisi, nomor RM lama dipertahankan dan baris dengan nomor RM yang sama akan di-update.
 */
class PatientImporter extends Importer
{
    use IndonesianImportNotification;

    protected static ?string $model = Patient::class;

    protected static bool $shouldPreventFormulaInjection = true;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('rm_number')
                ->label('No. RM')
                ->helperText('Opsional. Kosongkan agar dibuat otomatis.')
                ->guess(['no_rm', 'rm', 'nomor_rm'])
                ->rules(['nullable', 'max:30']),
            ImportColumn::make('name')
                ->label('Nama')
                ->guess(['nama', 'nama_pasien'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('nik')
                ->label('NIK')
                ->sensitive()
                ->castStateUsing(fn (?string $state): ?string => $state === null ? null : (preg_replace('/\D+/', '', $state) ?: null))
                ->rules(['nullable', 'digits:16']),
            ImportColumn::make('birth_place')
                ->label('Tempat lahir')
                ->guess(['tempat_lahir']),
            ImportColumn::make('birth_date')
                ->label('Tanggal lahir')
                ->helperText('Format dd/mm/yyyy atau yyyy-mm-dd.')
                ->guess(['tanggal_lahir', 'tgl_lahir'])
                ->requiredMapping()
                ->castStateUsing(fn (?string $state): ?string => self::parseDate($state))
                ->rules(['required', 'date', 'before_or_equal:today']),
            ImportColumn::make('gender')
                ->label('Jenis kelamin')
                ->helperText('L/P, laki-laki/perempuan, atau male/female.')
                ->guess(['jenis_kelamin', 'jk', 'sex'])
                ->requiredMapping()
                ->castStateUsing(fn (?string $state): ?string => self::parseGender($state))
                ->rules(['required', 'in:male,female']),
            ImportColumn::make('phone')
                ->label('No. HP')
                ->guess(['hp', 'no_hp', 'telepon', 'telp'])
                ->rules(['nullable', 'max:30']),
            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:255']),
            ImportColumn::make('address')
                ->label('Alamat')
                ->guess(['alamat']),
            ImportColumn::make('occupation')
                ->label('Pekerjaan')
                ->guess(['pekerjaan']),
            ImportColumn::make('allergies')
                ->label('Alergi')
                ->guess(['alergi']),
            ImportColumn::make('systemic_diseases')
                ->label('Penyakit sistemik')
                ->guess(['penyakit', 'riwayat_penyakit']),
        ];
    }

    public function resolveRecord(): Patient
    {
        if (filled($this->data['rm_number'] ?? null)) {
            return Patient::withTrashed()->firstOrNew(['rm_number' => $this->data['rm_number']]);
        }

        return new Patient;
    }

    public static function parseDate(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, trim($state));

                if ($date instanceof Carbon && $date->format($format) === trim($state)) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // coba format berikutnya
            }
        }

        return $state;
    }

    public static function parseGender(?string $state): ?string
    {
        return match (strtolower(trim((string) $state))) {
            'l', 'lk', 'laki-laki', 'laki laki', 'pria', 'male', 'm' => Gender::Male->value,
            'p', 'pr', 'perempuan', 'wanita', 'female', 'f', 'w' => Gender::Female->value,
            default => $state,
        };
    }
}
