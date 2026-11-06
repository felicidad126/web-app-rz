<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\IndonesianImportNotification;
use App\Models\Patient;
use App\Services\Patients\LegacyValue;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

/**
 * Import pasien dari sistem lama (CSV).
 *
 * Jika kolom no_rm diisi, nomor RM lama dipertahankan dan baris dengan nomor RM yang sama akan di-update.
 * Untuk berkas Excel klinik yang berantakan (NIK rusak, umur tanpa tanggal lahir, baris lanjutan) pakai perintah
 * `php artisan patients:migrate-legacy` yang punya mode uji coba dan laporan masalah.
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
                ->guess(['no_rm', 'rm', 'nomor_rm', 'nomor_mr'])
                ->castStateUsing(fn (?string $state): ?string => LegacyValue::rm($state))
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
                ->helperText('Format dd/mm/yyyy, yyyy-mm-dd, atau "17 Mei 1990".')
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
                ->castStateUsing(fn (?string $state): ?string => LegacyValue::phone($state)[0] ?? (blank($state) ? null : $state))
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

    /** Tanggal bebas format (lihat LegacyValue::date); bila tidak terbaca, nilai asli dikembalikan agar ditolak validasi. */
    public static function parseDate(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        return LegacyValue::date($state)?->toDateString() ?? $state;
    }

    public static function parseGender(?string $state): ?string
    {
        return LegacyValue::gender($state)->value ?? $state;
    }
}
