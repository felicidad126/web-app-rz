<?php

namespace Database\Seeders;

use App\Enums\OdontogramCategory;
use App\Enums\OdontogramScope;
use App\Enums\PayerType;
use App\Models\ConsentTemplate;
use App\Models\Icd10Code;
use App\Models\Icd9cmCode;
use App\Models\OdontogramCondition;
use App\Models\Payer;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/** Data referensi yang dibutuhkan di semua lingkungan (termasuk produksi). Idempoten. */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCodes(Icd10Code::class, database_path('data/icd10_dental_starter.csv'));
        $this->seedCodes(Icd9cmCode::class, database_path('data/icd9cm_dental_starter.csv'));
        $this->seedOdontogramConditions();
        $this->seedUnits();
        $this->seedConsentTemplates();

        Payer::withTrashed()->firstOrCreate(
            ['code' => 'UMUM'],
            ['name' => 'Umum (bayar sendiri)', 'type' => PayerType::General, 'is_active' => true],
        );
    }

    /** @param class-string<Icd10Code|Icd9cmCode> $model */
    /** Template persetujuan umum. Klinik WAJIB meninjau isinya sebelum dipakai. */
    private function seedConsentTemplates(): void
    {
        ConsentTemplate::firstOrCreate(['name' => 'Persetujuan tindakan kedokteran gigi (umum)'], [
            'is_active' => true,
            'body' => <<<'TXT'
Saya yang bertanda tangan di bawah ini menyatakan bahwa dokter {{dokter}} telah menjelaskan kepada saya
mengenai tindakan yang akan dilakukan pada pasien {{nama_pasien}} (No. RM {{no_rm}}, lahir {{tanggal_lahir}}):

Tindakan: {{tindakan}}

Penjelasan yang saya terima meliputi:
1. Diagnosis dan kondisi gigi/mulut pasien.
2. Tujuan dan tata cara tindakan.
3. Risiko dan kemungkinan komplikasi, termasuk nyeri, bengkak, perdarahan, atau reaksi terhadap obat/bius lokal.
4. Pilihan perawatan lain beserta risikonya, termasuk risiko bila tidak dilakukan tindakan.
5. Perkiraan hasil (prognosis) dan perkiraan biaya.

Saya telah diberi kesempatan bertanya dan semua pertanyaan saya sudah dijawab dengan jelas.
Dengan ini saya MENYETUJUI dilakukannya tindakan tersebut.

{{cabang}}, {{tanggal}}
TXT,
        ]);
    }

    private function seedCodes(string $model, string $path): void
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '\\');

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $data = array_combine($header, $row);

            $model::updateOrCreate(['code' => $data['code']], [
                'name' => $data['name'],
                'name_id' => $data['name_id'] ?: null,
            ]);
        }

        fclose($handle);
    }

    /**
     * Draft kode kondisi odontogram. WAJIB dikonfirmasi dengan dokter gigi klinik (Planning v2 §5.3).
     */
    private function seedOdontogramConditions(): void
    {
        $c = OdontogramCategory::Condition;
        $r = OdontogramCategory::Restoration;
        $p = OdontogramCategory::Prosthesis;
        $tooth = OdontogramScope::Tooth;
        $surface = OdontogramScope::Surface;

        $rows = [
            ['sou', 'Normal / sehat', $c, $tooth, '#22c55e'],
            ['car', 'Karies', $c, $surface, '#f79009'],
            ['nvt', 'Gigi non-vital', $c, $tooth, '#7c3aed'],
            ['rrx', 'Sisa akar', $c, $tooth, '#b91c1c'],
            ['mis', 'Gigi hilang', $c, $tooth, '#111827'],
            ['une', 'Belum erupsi', $c, $tooth, '#9ca3af'],
            ['pre', 'Erupsi sebagian', $c, $tooth, '#9ca3af'],
            ['ano', 'Anomali', $c, $tooth, '#f59e0b'],
            ['cfr', 'Fraktur', $c, $surface, '#dc2626'],
            ['att', 'Atrisi', $c, $surface, '#d97706'],
            ['abr', 'Abrasi', $c, $surface, '#d97706'],
            ['amf', 'Tambalan amalgam', $r, $surface, '#344054'],
            ['cof', 'Tambalan komposit', $r, $surface, '#12b76a'],
            ['gif', 'Tambalan GIC', $r, $surface, '#0891b2'],
            ['fis', 'Fissure sealant', $r, $surface, '#059669'],
            ['inl', 'Inlay', $r, $surface, '#4f46e5'],
            ['onl', 'Onlay', $r, $surface, '#4f46e5'],
            ['rct', 'Perawatan saluran akar', $r, $tooth, '#be185d'],
            ['fmc', 'Full metal crown', $p, $tooth, '#6b7280'],
            ['poc', 'Porcelain crown', $p, $tooth, '#0ea5e9'],
            ['mpc', 'Metal-porcelain crown', $p, $tooth, '#0284c7'],
            ['meb', 'Metal bridge', $p, $tooth, '#6b7280'],
            ['pob', 'Porcelain bridge', $p, $tooth, '#0ea5e9'],
            ['pon', 'Pontic', $p, $tooth, '#64748b'],
            ['abu', 'Abutment', $p, $tooth, '#64748b'],
            ['ipx', 'Implan', $p, $tooth, '#1d4ed8'],
            ['prd', 'Gigi tiruan sebagian lepasan', $p, $tooth, '#a855f7'],
            ['fld', 'Gigi tiruan penuh', $p, $tooth, '#a855f7'],
        ];

        foreach ($rows as $sort => [$code, $name, $category, $scope, $color]) {
            OdontogramCondition::firstOrCreate(['code' => $code], [
                'name' => $name,
                'category' => $category,
                'applies_to' => $scope,
                'color' => $color,
                'sort' => ($sort + 1) * 10,
                'is_active' => true,
            ]);
        }
    }

    private function seedUnits(): void
    {
        foreach (['pcs', 'tablet', 'kapsul', 'strip', 'box', 'botol', 'tube', 'ml', 'gram', 'ampul', 'cartridge', 'vial', 'sachet', 'pack', 'set'] as $name) {
            Unit::firstOrCreate(['name' => $name]);
        }
    }
}
