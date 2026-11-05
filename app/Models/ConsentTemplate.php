<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Template informed consent. Isi berupa teks biasa dengan placeholder {{nama_pasien}} dst.
 *
 * @property int $id
 * @property string $name
 * @property string $body
 * @property bool $is_active
 */
#[Fillable(['name', 'body', 'is_active'])]
class ConsentTemplate extends Model
{
    use LogsAudit;
    use SoftDeletes;

    /** @var array<string, string> placeholder => keterangan */
    public const PLACEHOLDERS = [
        'nama_pasien' => 'Nama pasien',
        'no_rm' => 'Nomor rekam medis',
        'tanggal_lahir' => 'Tanggal lahir pasien',
        'tindakan' => 'Tindakan yang akan dilakukan',
        'dokter' => 'Nama dokter',
        'cabang' => 'Nama cabang',
        'tanggal' => 'Tanggal hari ini',
    ];

    /** @param array<string, string|null> $values */
    public static function render(string $body, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $m): string => array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0],
            $body,
        );
    }

    /** @return array<string, string> */
    public static function valuesFor(Visit $visit, ?string $procedure = null): array
    {
        $visit->loadMissing(['patient', 'practitioner.user', 'branch']);

        return [
            'nama_pasien' => $visit->patient->name,
            'no_rm' => $visit->patient->rm_number,
            'tanggal_lahir' => $visit->patient->birth_date->format('d/m/Y'),
            'tindakan' => $procedure ?? '',
            'dokter' => $visit->practitioner->user->name,
            'cabang' => $visit->branch->name,
            'tanggal' => now()->translatedFormat('j F Y'),
        ];
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
