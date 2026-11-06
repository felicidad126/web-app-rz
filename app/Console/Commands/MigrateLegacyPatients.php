<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\Patients\LegacyPatientMigration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Migrasi master pasien dari Excel/CSV sistem lama. Tanpa --commit hanya uji coba (tidak ada yang disimpan).
 * Laporan masalah (nomor baris, No. RM, masalah; tanpa nama/NIK) disimpan di disk privat.
 */
#[Signature('patients:migrate-legacy
    {file : Path berkas .xlsx atau .csv}
    {--sheet= : Nama sheet (bawaan: sheet pertama yang punya judul kolom pasien)}
    {--branch= : Kode cabang tempat pasien terdaftar}
    {--commit : Simpan ke database (tanpa ini hanya uji coba)}')]
#[Description('Migrasi data pasien lama dari Excel/CSV (uji coba dulu, lalu --commit)')]
class MigrateLegacyPatients extends Command
{
    public function handle(): int
    {
        $branch = null;

        if (filled($code = $this->option('branch'))) {
            $branch = Branch::where('code', $code)->first();

            if ($branch === null) {
                $this->error("Cabang dengan kode {$code} tidak ditemukan.");

                return self::FAILURE;
            }
        }

        try {
            [$sheet, $rows] = LegacyPatientMigration::pickSheet(
                LegacyPatientMigration::readFile((string) $this->argument('file')),
                $this->option('sheet') ?: null,
            );
            $migration = (new LegacyPatientMigration($branch))->run($rows, commit: (bool) $this->option('commit'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $stats = $migration->stats();
        $issues = $migration->issues();
        $committed = (bool) $this->option('commit');

        $this->info(($committed ? 'DISIMPAN' : 'UJI COBA (belum disimpan)')." · sheet \"{$sheet}\"");
        $this->table(['Keterangan', 'Jumlah'], [
            ['Baris berisi data', $stats['rows']],
            ['Dilewati (judul/catatan)', $stats['skipped']],
            ['Digabung (baris lanjutan / RM sama)', $stats['merged']],
            ['Pasien baru', $stats['create']],
            ['Pasien lama dilengkapi', $stats['update']],
            ['Sudah ada, tidak berubah', $stats['unchanged']],
            ['Ditolak', $stats['rejected']],
            ['Peringatan', count(array_filter($issues, fn (array $i): bool => $i['level'] === LegacyPatientMigration::LEVEL_WARNING))],
        ]);

        if ($issues !== []) {
            $path = 'migrasi/laporan-pasien-'.now()->format('Ymd-His').'.csv';
            $lines = ['Baris;No. RM;Tingkat;Masalah'];

            foreach ($issues as $issue) {
                $lines[] = implode(';', [$issue['row'], $issue['rm'] ?? '', $issue['level'], str_replace(';', ',', $issue['message'])]);
            }

            Storage::disk('local')->put($path, "\u{FEFF}".implode("\n", $lines)."\n");

            $this->table(['Baris', 'No. RM', 'Tingkat', 'Masalah'], array_map(
                fn (array $i): array => [$i['row'], $i['rm'] ?? '—', $i['level'], $i['message']],
                array_slice($issues, 0, 40),
            ));

            if (count($issues) > 40) {
                $this->line('… '.(count($issues) - 40).' masalah lain ada di laporan.');
            }

            $this->line('Laporan lengkap: '.Storage::disk('local')->path($path));
        }

        if (! $committed) {
            $this->warn('Belum ada yang disimpan. Periksa laporan, rapikan berkas bila perlu, lalu jalankan ulang dengan --commit.');
        }

        return self::SUCCESS;
    }
}
