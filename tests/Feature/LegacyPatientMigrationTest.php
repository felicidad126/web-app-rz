<?php

use App\Console\Commands\MigrateLegacyPatients;
use App\Models\Branch;
use App\Models\Patient;
use App\Services\Patients\LegacyPatientMigration;
use App\Services\Patients\LegacyValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

// Semua data di sini fiktif. Jangan pernah menyalin data pasien asli ke repositori.

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-06 10:00'));
    config()->set('clinic.rm_number.prefix', 'MR');
    config()->set('clinic.rm_number.pad', 6);
    $this->branch = Branch::factory()->create(['code' => 'KMG']);

    $this->xlsx = function (array $rows): string {
        $path = tempnam(sys_get_temp_dir(), 'legacy-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    };

    // Bentuk mengikuti workbook klinik: judul, kolom kosong di depan, judul bulan, baris lanjutan tanpa nama.
    $this->rows = [
        ['Data pasien klinik (fiktif)'],
        [null, 'Tanggal', 'nomor Mr', 'nomor KTP', 'Nama Pasien', 'Umur', 'Jenis kelamin', 'Perawatan', 'Alamat', 'No HP'],
        [null, 'Selasa, 22 April 2025', ' MR000101', 3271234567890000, 'Budi Contoh', 30, 'L', 'Scaling', 'Jl. Contoh 1', 81234567890],
        [null, null, null, null, null, null, null, 'Tambal komposit', null, null],
        [null, 'Mei 2025'],
        [null, 'Rabu,07/05/2025', 'MR000102', '3271015505900002', 'Sari Contoh', '35 th', 'P', 'Cabut', 'Jl. Contoh 2', '+62 813-1111-2222'],
        [null, 'Kamis,03-Juli-2025', 'MR000101', null, 'Budi Contoh', 30, 'L', 'Kontrol', null, null],
        [null, 'Minggu/26/10/2025', 'MR000103', null, 'Tanpa Umur', null, 'L', 'Konsultasi', null, null],
        [null, '01/08/2025', 'MR000104', null, 'Anak Contoh', '8 bulan', 'X', 'Konsultasi', null, null],
        [null, '02/08/2025', 'MR000105', null, 'Nama Di Excel', 40, 'P', 'Scaling', 'Jl. Baru', '0812 0000 1111'],
        [null, '03/08/2025', 'MR000106', '3271010101850003', 'Kembar NIK', 41, 'L', 'Scaling', null, null],
    ];

    $this->existing = Patient::factory()->create(['rm_number' => 'MR000105', 'name' => 'Nama Di Aplikasi', 'phone' => null, 'address' => null, 'gender' => 'female']);
    Patient::factory()->create(['rm_number' => 'P-LAIN', 'nik' => '3271010101850003']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('merapikan nilai dari Excel lama', function () {
    expect(LegacyValue::date('Selasa, 22 April 2025')?->toDateString())->toBe('2025-04-22')
        ->and(LegacyValue::date('Rabu,07/05/2025')?->toDateString())->toBe('2025-05-07')
        ->and(LegacyValue::date('Kamis,03-Juli-2025')?->toDateString())->toBe('2025-07-03')
        ->and(LegacyValue::date('Minggu/26/10/2025')?->toDateString())->toBe('2025-10-26')
        ->and(LegacyValue::date('17 Agt 98')?->toDateString())->toBe('1998-08-17')
        ->and(LegacyValue::date(45769)?->toDateString())->toBe('2025-04-22')
        ->and(LegacyValue::date('Mei 2025'))->toBeNull()
        ->and(LegacyValue::date('31/02/2025'))->toBeNull()
        ->and(LegacyValue::nik(3271234567890000)[0])->toBeNull()
        ->and(LegacyValue::nik('3.27123E+15')[0])->toBeNull()
        ->and(LegacyValue::nik('32712345678900')[1])->toContain('14 digit')
        ->and(LegacyValue::nik('3271015505900000')[0])->toBeNull() // nomor urut 0000
        ->and(LegacyValue::nik(' 3271-0155-0590-0002 ')[0])->toBe('3271015505900002')
        ->and(LegacyValue::nikBirth('3271015505900002'))->gender->value->toBe('female')
        ->and(LegacyValue::phone(81234567890)[0])->toBe('081234567890')
        ->and(LegacyValue::phone('+62 813-1111-2222')[0])->toBe('081311112222')
        ->and(LegacyValue::phone('12345')[1])->not->toBeNull()
        ->and(LegacyValue::age('35 th'))->toBe(35)
        ->and(LegacyValue::age('8 bulan'))->toBe(0)
        ->and(LegacyValue::rm(' mr 000101'))->toBe('MR000101');
});

it('uji coba tidak menyimpan apa pun dan melaporkan masalah tanpa nama/NIK', function () {
    [$sheet, $rows] = LegacyPatientMigration::pickSheet(LegacyPatientMigration::readFile(($this->xlsx)($this->rows)));
    $migration = (new LegacyPatientMigration($this->branch))->run($rows);

    expect(Patient::count())->toBe(2)
        ->and($migration->stats())->toMatchArray(['skipped' => 1, 'merged' => 2, 'create' => 2, 'update' => 1, 'rejected' => 3]);

    $issues = collect($migration->issues());
    $rejected = $issues->where('level', LegacyPatientMigration::LEVEL_ERROR)->pluck('message', 'rm');

    expect($rejected['MR000103'])->toBe('Tanggal lahir dan umur kosong')
        ->and($rejected['MR000104'])->toBe('Jenis kelamin kosong atau tidak dikenali')
        ->and($rejected['MR000106'])->toContain('NIK sudah dipakai pasien RM P-LAIN')
        ->and($issues->where('rm', 'MR000101')->pluck('message')->implode(' | '))->toContain('NIK tersimpan sebagai angka', 'diperkirakan dari umur 30')
        ->and(json_encode($issues))->not->toContain('Budi')->not->toContain('3271015505900002');
});

it('menyimpan pasien lama sekali saja, melengkapi yang sudah ada, dan memajukan urutan RM', function () {
    $file = ($this->xlsx)($this->rows);
    [, $rows] = LegacyPatientMigration::pickSheet(LegacyPatientMigration::readFile($file));
    (new LegacyPatientMigration($this->branch))->run($rows, commit: true);

    $budi = Patient::where('rm_number', 'MR000101')->sole();
    expect($budi)->nik->toBeNull()->phone->toBe('081234567890')->registered_branch_id->toBe($this->branch->id)
        ->and($budi->birth_date->toDateString())->toBe('1994-10-22') // 22/04/2025 − 30 th − 6 bln
        ->and($budi->notes)->toContain('[Data sistem lama] Kunjungan di sistem lama: 22/04/2025–03/07/2025 (2×).')
        ->toContain('Scaling; Tambal komposit; Kontrol')->toContain('perkiraan dari umur 30 th per 22/04/2025');

    $sari = Patient::whereNik('3271015505900002')->sole();
    expect($sari)->rm_number->toBe('MR000102')->phone->toBe('081311112222')
        ->and($sari->gender->value)->toBe('female')
        ->and($sari->birth_date->toDateString())->toBe('1990-05-15'); // dari NIK

    expect($this->existing->fresh())->name->toBe('Nama Di Aplikasi')->phone->toBe('081200001111')->address->toBe('Jl. Baru')
        ->and(Patient::where('rm_number', 'like', 'MR%')->count())->toBe(3)
        ->and(Patient::factory()->create()->rm_number)->toBe('MR000106'); // tidak bertabrakan dengan RM lama

    // Dijalankan ulang: tidak ada pasien ganda dan catatan tidak ditambah dua kali.
    $again = (new LegacyPatientMigration($this->branch))->run($rows, commit: true);
    expect($again->stats())->toMatchArray(['create' => 0, 'update' => 0, 'unchanged' => 3])
        ->and(substr_count((string) $budi->fresh()->notes, '[Data sistem lama]'))->toBe(1);
});

it('membaca CSV berpemisah titik koma dan menjalankan perintah artisan', function () {
    Storage::fake('local');
    $csv = tempnam(sys_get_temp_dir(), 'legacy-').'.csv';
    file_put_contents($csv, "\u{FEFF}No. RM;Nama;Tgl Lahir;JK;HP\nMR000201;Dewi Contoh;17/08/1995;Perempuan;0812-3333-4444\nMR000202;Tanpa JK;01/01/1990;;\n");

    $this->artisan(MigrateLegacyPatients::class, ['file' => $csv, '--branch' => 'XXX'])->assertFailed();

    $this->artisan(MigrateLegacyPatients::class, ['file' => $csv, '--branch' => 'KMG'])
        ->expectsOutputToContain('UJI COBA')
        ->assertSuccessful();
    expect(Patient::where('rm_number', 'MR000201')->exists())->toBeFalse()
        ->and(Storage::disk('local')->files('migrasi'))->toHaveCount(1);

    $this->artisan(MigrateLegacyPatients::class, ['file' => $csv, '--commit' => true])->assertSuccessful();
    expect(Patient::where('rm_number', 'MR000201')->sole())->phone->toBe('081233334444')
        ->and(Patient::where('rm_number', 'MR000201')->sole()->birth_date->toDateString())->toBe('1995-08-17')
        ->and(Patient::where('rm_number', 'MR000202')->exists())->toBeFalse();
});
