<?php

namespace App\Services\Patients;

use App\Models\Branch;
use App\Models\Patient;
use App\Support\BlindIndex;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Migrasi master pasien dari Excel/CSV sistem lama (satu kali, lewat `php artisan patients:migrate-legacy`).
 *
 * - Judul kolom dikenali dari nama yang lazim ("nomor Mr", "nomor KTP", "Nama Pasien", "Umur", "Jenis kelamin", …).
 * - Baris judul bulan dan baris kosong dilewati. Baris lanjutan (tanpa nama, untuk tindakan tambahan) digabung ke
 *   pasien di atasnya. Beberapa baris dengan No. RM yang sama digabung menjadi satu pasien.
 * - NIK yang rusak (tersimpan sebagai angka, bukan 16 digit) tidak diisi dan dilaporkan agar dikumpulkan ulang.
 * - Tanggal lahir diambil dari kolom tanggal lahir, lalu dari NIK, lalu diperkirakan dari umur (dicatat di catatan).
 * - Pasien yang sudah ada (No. RM sama) hanya diisi kolom yang masih kosong; data di aplikasi tidak ditimpa.
 * - Riwayat kunjungan lama hanya disimpan sebagai catatan pasien, bukan rekam medis (tidak terkirim ke SATUSEHAT).
 *
 * Laporan masalah hanya memuat nomor baris & No. RM, tidak memuat nama atau NIK.
 */
final class LegacyPatientMigration
{
    public const NOTE_MARKER = '[Data sistem lama]';

    public const LEVEL_ERROR = 'Ditolak';

    public const LEVEL_WARNING = 'Peringatan';

    /** @var array<string, list<string>> kolom → judul yang dikenali (huruf kecil, tanpa spasi/tanda baca) */
    private const COLUMNS = [
        'rm' => ['norm', 'nomorrm', 'nomormr', 'nomr', 'rm', 'mr', 'normlama', 'nomorrekammedis', 'rekammedis'],
        'nik' => ['nik', 'nomorktp', 'noktp', 'ktp', 'nomornik', 'nikktp', 'noidentitas'],
        'name' => ['nama', 'namapasien', 'namalengkap'],
        'birth_date' => ['tanggallahir', 'tgllahir', 'tgllhr', 'tanggallahirpasien'],
        'birth_place' => ['tempatlahir'],
        'age' => ['umur', 'usia'],
        'gender' => ['jeniskelamin', 'jk', 'kelamin', 'lp', 'sex', 'gender'],
        'phone' => ['hp', 'nohp', 'nomorhp', 'telepon', 'telp', 'notelp', 'notlp', 'nomortelepon', 'wa', 'nowa', 'nomorwa'],
        'address' => ['alamat'],
        'occupation' => ['pekerjaan'],
        'allergies' => ['alergi', 'riwayatalergi'],
        'systemic_diseases' => ['penyakit', 'riwayatpenyakit', 'penyakitsistemik'],
        'visit_date' => ['tanggal', 'tgl', 'tanggalkunjungan', 'tglkunjungan', 'tanggalperiksa'],
        'treatment' => ['perawatan', 'tindakan', 'jenisperawatan'],
    ];

    /** Kolom teks yang diisi apa adanya (dirapikan spasinya). */
    private const PLAIN_FIELDS = ['birth_place', 'address', 'occupation', 'allergies', 'systemic_diseases'];

    /** @var list<array{row: int, rm: ?string, level: string, message: string}> */
    private array $issues = [];

    /** @var list<string|null> blind index NIK yang sudah dipakai pasien lain di berkas yang sama */
    private array $seenNik = [];

    /** @var array{rows: int, skipped: int, merged: int, create: int, update: int, unchanged: int, rejected: int} */
    private array $stats = ['rows' => 0, 'skipped' => 0, 'merged' => 0, 'create' => 0, 'update' => 0, 'unchanged' => 0, 'rejected' => 0];

    public function __construct(private readonly ?Branch $branch = null) {}

    /**
     * Baca semua sheet: nama sheet → daftar baris (nomor baris fisik → isi sel).
     *
     * @return array<string, array<int, list<mixed>>>
     */
    public static function readFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Berkas tidak ditemukan: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            $options = new XlsxOptions;
            $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
            $reader = new XlsxReader($options);
        } elseif (in_array($extension, ['csv', 'txt'], true)) {
            $options = new CsvOptions;
            $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
            $options->FIELD_DELIMITER = self::detectDelimiter($path);
            $reader = new CsvReader($options);
        } else {
            throw new RuntimeException('Format berkas harus .xlsx atau .csv.');
        }

        $reader->open($path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            $number = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $rows[++$number] = $row->toArray();
            }

            $sheets[$sheet->getName() ?: 'Sheet'] = $rows;
        }

        $reader->close();

        return $sheets;
    }

    /**
     * Pilih sheet: yang disebut namanya, atau sheet pertama yang punya judul kolom pasien.
     *
     * @param  array<string, array<int, list<mixed>>>  $sheets
     * @return array{0: string, 1: array<int, list<mixed>>}
     */
    public static function pickSheet(array $sheets, ?string $name = null): array
    {
        if ($name !== null) {
            if (! isset($sheets[$name])) {
                throw new RuntimeException("Sheet \"{$name}\" tidak ada. Sheet yang tersedia: ".implode(', ', array_keys($sheets)));
            }

            return [$name, $sheets[$name]];
        }

        foreach ($sheets as $sheetName => $rows) {
            if (self::findHeader($rows) !== null) {
                return [$sheetName, $rows];
            }
        }

        throw new RuntimeException('Tidak ada sheet dengan judul kolom pasien (mis. "Nama", "No. RM", "Jenis kelamin").');
    }

    /**
     * Proses baris. Tanpa $commit tidak ada yang disimpan (uji coba); dengan $commit semua disimpan dalam satu transaksi.
     *
     * @param  array<int, list<mixed>>  $rows
     */
    public function run(array $rows, bool $commit = false): self
    {
        $header = self::findHeader($rows);

        if ($header === null) {
            throw new RuntimeException('Judul kolom tidak ditemukan (perlu minimal kolom nama dan No. RM/jenis kelamin/umur).');
        }

        [$headerRow, $map] = $header;
        $groups = $this->group($rows, $headerRow, $map);
        $plans = array_map(fn (array $group): ?array => $this->plan($group), $groups);

        if ($commit) {
            DB::transaction(function () use ($plans): void {
                foreach (array_filter($plans) as $plan) {
                    $this->save($plan);
                }
            });
        }

        return $this;
    }

    /** @return list<array{row: int, rm: ?string, level: string, message: string}> */
    public function issues(): array
    {
        return $this->issues;
    }

    /** @return array{rows: int, skipped: int, merged: int, create: int, update: int, unchanged: int, rejected: int} */
    public function stats(): array
    {
        return $this->stats;
    }

    /**
     * @param  array<int, list<mixed>>  $rows
     * @return array{0: int, 1: array<string, int>}|null [nomor baris judul, kolom → indeks]
     */
    private static function findHeader(array $rows): ?array
    {
        foreach (array_slice($rows, 0, 25, true) as $number => $cells) {
            $map = [];

            foreach ($cells as $index => $cell) {
                $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) LegacyValue::text($cell))) ?? '';

                foreach (self::COLUMNS as $field => $aliases) {
                    if ($key !== '' && ! isset($map[$field]) && in_array($key, $aliases, true)) {
                        $map[$field] = $index;
                    }
                }
            }

            if (isset($map['name']) && (isset($map['rm']) || isset($map['gender']) || isset($map['age']) || isset($map['birth_date']))) {
                return [$number, $map];
            }
        }

        return null;
    }

    /**
     * Gabungkan baris per pasien: No. RM sama → satu pasien; baris tanpa nama & RM yang berisi tindakan → lanjutan.
     *
     * @param  array<int, list<mixed>>  $rows
     * @param  array<string, int>  $map
     * @return list<array{row: int, rm: ?string, values: array<string, mixed>, visits: list<Carbon>, treatments: list<string>, conflicts: list<string>, age_at?: ?Carbon}>
     */
    private function group(array $rows, int $headerRow, array $map): array
    {
        $groups = [];
        $byRm = [];
        $last = null;

        foreach ($rows as $number => $cells) {
            if ($number <= $headerRow) {
                continue;
            }

            $values = [];

            foreach ($map as $field => $index) {
                $value = $cells[$index] ?? null;
                $values[$field] = LegacyValue::text($value) === null ? null : $value;
            }

            $filled = count(array_filter($cells, fn (mixed $cell): bool => LegacyValue::text($cell) !== null));

            if ($filled === 0) {
                continue;
            }

            $this->stats['rows']++;
            $rm = LegacyValue::rm($values['rm'] ?? null);
            $name = LegacyValue::text($values['name'] ?? null);

            if ($name === null && $rm === null) {
                // Baris lanjutan (tindakan tambahan di kunjungan yang sama) atau judul bulan/catatan.
                if ($last !== null && LegacyValue::text($values['treatment'] ?? null) !== null) {
                    $this->appendVisit($groups[$last], $values);
                    $this->stats['merged']++;
                } else {
                    $this->stats['skipped']++;
                }

                continue;
            }

            if ($rm !== null && isset($byRm[$rm])) {
                $this->mergeInto($groups[$byRm[$rm]], $values);
                $this->stats['merged']++;
                $last = $byRm[$rm];

                continue;
            }

            if ($name === null) {
                $this->issue($number, $rm, self::LEVEL_ERROR, 'Nama pasien kosong');
                $this->stats['rejected']++;
                $last = null;

                continue;
            }

            $groups[] = ['row' => $number, 'rm' => $rm, 'values' => $values, 'visits' => [], 'treatments' => [], 'conflicts' => []];
            $last = array_key_last($groups);
            $this->appendVisit($groups[$last], $values);

            if ($rm !== null) {
                $byRm[$rm] = $last;
            }
        }

        return $groups;
    }

    /**
     * @param  array{row: int, rm: ?string, values: array<string, mixed>, visits: list<Carbon>, treatments: list<string>, conflicts: list<string>, age_at?: ?Carbon}  $group
     * @param  array<string, mixed>  $values
     */
    private function mergeInto(array &$group, array $values): void
    {
        foreach ($values as $field => $value) {
            if (in_array($field, ['visit_date', 'treatment', 'age'], true) || $value === null) {
                continue;
            }

            $current = $group['values'][$field] ?? null;

            if ($current === null) {
                $group['values'][$field] = $value;
            } elseif (in_array($field, ['name', 'nik', 'gender', 'birth_date'], true)
                && mb_strtolower((string) LegacyValue::text($current)) !== mb_strtolower((string) LegacyValue::text($value))
                && ! in_array($field, $group['conflicts'], true)) {
                $group['conflicts'][] = $field;
            }
        }

        $this->appendVisit($group, $values);
    }

    /**
     * @param  array{row: int, rm: ?string, values: array<string, mixed>, visits: list<Carbon>, treatments: list<string>, conflicts: list<string>, age_at?: ?Carbon}  $group
     * @param  array<string, mixed>  $values
     */
    private function appendVisit(array &$group, array $values): void
    {
        $date = LegacyValue::date($values['visit_date'] ?? null);

        if ($date !== null) {
            $group['visits'][] = $date;
        }

        $treatment = LegacyValue::text($values['treatment'] ?? null);

        if ($treatment !== null && ! in_array($treatment, $group['treatments'], true)) {
            $group['treatments'][] = $treatment;
        }

        // Umur yang tercatat paling awal (beserta tanggal kunjungannya) dipakai untuk memperkirakan tanggal lahir.
        if (! array_key_exists('age_at', $group) && LegacyValue::age($values['age'] ?? null) !== null) {
            $group['values']['age'] = $values['age'];
            $group['age_at'] = $date;
        }
    }

    /**
     * @param  array{row: int, rm: ?string, values: array<string, mixed>, visits: list<Carbon>, treatments: list<string>, conflicts: list<string>, age_at?: ?Carbon}  $group
     * @return array{patient: ?Patient, attributes: array<string, mixed>, note: string}|null
     */
    private function plan(array $group): ?array
    {
        $row = $group['row'];
        $rm = $group['rm'];
        $v = $group['values'];
        $warn = fn (string $message) => $this->issue($row, $rm, self::LEVEL_WARNING, $message);
        $reject = function (string $message) use ($row, $rm): null {
            $this->issue($row, $rm, self::LEVEL_ERROR, $message);
            $this->stats['rejected']++;

            return null;
        };

        foreach ($group['conflicts'] as $field) {
            $warn('Isi kolom '.self::fieldLabel($field).' berbeda antarbaris dengan No. RM sama; dipakai baris pertama');
        }

        [$nik, $nikProblem] = LegacyValue::nik($v['nik'] ?? null);

        if ($nikProblem !== null) {
            $warn($nikProblem);
        }

        $fromNik = $nik !== null ? LegacyValue::nikBirth($nik) : null;

        // Jenis kelamin: kolom, lalu NIK.
        $gender = LegacyValue::gender($v['gender'] ?? null);

        if ($gender === null && ($v['gender'] ?? null) !== null) {
            $warn('Jenis kelamin "'.LegacyValue::text($v['gender']).'" tidak dikenali');
        }

        if ($gender !== null && $fromNik !== null && $fromNik['gender'] !== $gender) {
            $warn('Jenis kelamin berbeda dengan yang tertanam di NIK; dipakai isi kolom');
        }

        $gender ??= $fromNik['gender'] ?? null;

        if ($gender === null) {
            return $reject('Jenis kelamin kosong atau tidak dikenali');
        }

        // Tanggal lahir: kolom, lalu NIK, lalu perkiraan dari umur.
        $notes = [];
        $age = LegacyValue::age($v['age'] ?? null);
        $ageAt = $group['age_at'] ?? today();
        $birthDate = LegacyValue::date($v['birth_date'] ?? null);

        if ($birthDate === null && ($v['birth_date'] ?? null) !== null) {
            $warn('Tanggal lahir "'.LegacyValue::text($v['birth_date']).'" tidak terbaca');
        }

        if ($birthDate === null && $fromNik !== null) {
            $candidates = array_values(array_filter($fromNik['dates'], fn (Carbon $date): bool => $date->diffInYears(today()) <= 100));
            $matching = $age === null ? $candidates : array_values(array_filter($candidates, fn (Carbon $date): bool => abs((int) $date->diffInYears($ageAt) - $age) <= 1));
            $birthDate = $matching[0] ?? $candidates[0] ?? null;

            if ($birthDate !== null && $age !== null && $matching === []) {
                $warn('Umur tidak cocok dengan tanggal lahir di NIK; dipakai tanggal dari NIK');
            }
        }

        if ($birthDate === null && $age !== null) {
            $birthDate = $ageAt->copy()->subYears($age)->subMonths(6);
            $warn("Tanggal lahir diperkirakan dari umur {$age} th; lengkapi saat pasien datang");
            $notes[] = "Tanggal lahir perkiraan dari umur {$age} th per ".$ageAt->format('d/m/Y').'.';
        }

        if ($birthDate === null) {
            return $reject('Tanggal lahir dan umur kosong');
        }

        if ($birthDate->isFuture() || $birthDate->year < 1900) {
            return $reject('Tanggal lahir di luar rentang wajar');
        }

        [$phone, $phoneProblem] = LegacyValue::phone($v['phone'] ?? null);

        if ($phoneProblem !== null) {
            $warn($phoneProblem);
        }

        $attributes = [
            'rm_number' => $rm,
            'name' => (string) LegacyValue::text($v['name'] ?? null),
            'nik' => $nik,
            'birth_date' => $birthDate->toDateString(),
            'gender' => $gender->value,
            'phone' => $phone,
        ];

        foreach (self::PLAIN_FIELDS as $field) {
            $attributes[$field] = LegacyValue::text($v[$field] ?? null);
        }

        $existing = $rm !== null ? Patient::withTrashed()->where('rm_number', $rm)->first() : null;

        if ($existing?->trashed()) {
            return $reject('No. RM sudah ada tetapi pasiennya diarsipkan; pulihkan dulu atau ubah No. RM');
        }

        if ($nik !== null) {
            $owner = Patient::withTrashed()->whereNik($nik)->first();

            if ($owner !== null && $owner->getKey() !== $existing?->getKey()) {
                return $reject("NIK sudah dipakai pasien RM {$owner->rm_number}; periksa apakah orang yang sama");
            }

            if (in_array(BlindIndex::hash($nik), $this->seenNik, true)) {
                $warn('NIK sama dengan pasien lain di berkas ini; NIK tidak diisi');
                $attributes['nik'] = null;
            } else {
                $this->seenNik[] = BlindIndex::hash($nik);
            }
        }

        $visits = collect($group['visits'])->sort()->values();

        if ($visits->isNotEmpty()) {
            $range = $visits->first()->format('d/m/Y').($visits->count() > 1 ? '–'.$visits->last()->format('d/m/Y') : '');
            array_unshift($notes, "Kunjungan di sistem lama: {$range} ({$visits->count()}×).");
        }

        if ($group['treatments'] !== []) {
            $notes[] = 'Perawatan tercatat: '.implode('; ', $group['treatments']).'.';
        }

        $plan = [
            'patient' => $existing,
            'attributes' => $attributes,
            'note' => $notes === [] ? '' : self::NOTE_MARKER.' '.implode(' ', $notes),
        ];

        $action = $existing === null ? 'create' : ($this->changes($existing, $plan) === [] ? 'unchanged' : 'update');
        $this->stats[$action]++;

        return $plan;
    }

    /**
     * Pasien yang sudah ada hanya diisi kolom yang masih kosong; catatan riwayat lama ditambahkan sekali.
     *
     * @param  array{patient: ?Patient, attributes: array<string, mixed>, note: string}  $plan
     * @return array<string, mixed>
     */
    private function changes(Patient $patient, array $plan): array
    {
        $changes = [];

        foreach ($plan['attributes'] as $field => $value) {
            if ($value !== null && blank($patient->getAttribute($field))) {
                $changes[$field] = $value;
            }
        }

        $notes = (string) $patient->getAttribute('notes');

        if ($plan['note'] !== '' && ! str_contains($notes, self::NOTE_MARKER)) {
            $changes['notes'] = trim($notes."\n".$plan['note']);
        }

        return $changes;
    }

    /** @param array{patient: ?Patient, attributes: array<string, mixed>, note: string} $plan */
    private function save(array $plan): void
    {
        if ($plan['patient'] !== null) {
            $changes = $this->changes($plan['patient'], $plan);

            if ($changes !== []) {
                $plan['patient']->update($changes);
            }

            return;
        }

        Patient::create([
            ...$plan['attributes'],
            'notes' => $plan['note'] === '' ? null : $plan['note'],
            'registered_branch_id' => $this->branch?->getKey(),
        ]);
    }

    private function issue(int $row, ?string $rm, string $level, string $message): void
    {
        $this->issues[] = ['row' => $row, 'rm' => $rm, 'level' => $level, 'message' => $message];
    }

    private static function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => 'nama',
            'nik' => 'NIK',
            'gender' => 'jenis kelamin',
            'birth_date' => 'tanggal lahir',
            default => $field,
        };
    }

    private static function detectDelimiter(string $path): string
    {
        $line = (string) fgets($handle = fopen($path, 'r') ?: throw new RuntimeException('Berkas tidak bisa dibuka.'));
        fclose($handle);
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
