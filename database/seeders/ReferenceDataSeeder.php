<?php

namespace Database\Seeders;

use App\Enums\OdontogramCategory;
use App\Enums\OdontogramScope;
use App\Enums\PayerType;
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

        Payer::withTrashed()->firstOrCreate(
            ['code' => 'UMUM'],
            ['name' => 'Umum (bayar sendiri)', 'type' => PayerType::General, 'is_active' => true],
        );
    }

    /** @param class-string<Icd10Code|Icd9cmCode> $model */
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
            ['car', 'Karies', $c, $surface, '#ef4444'],
            ['nvt', 'Gigi non-vital', $c, $tooth, '#7c3aed'],
            ['rrx', 'Sisa akar', $c, $tooth, '#b91c1c'],
            ['mis', 'Gigi hilang', $c, $tooth, '#111827'],
            ['une', 'Belum erupsi', $c, $tooth, '#9ca3af'],
            ['pre', 'Erupsi sebagian', $c, $tooth, '#9ca3af'],
            ['ano', 'Anomali', $c, $tooth, '#f59e0b'],
            ['cfr', 'Fraktur', $c, $surface, '#dc2626'],
            ['att', 'Atrisi', $c, $surface, '#d97706'],
            ['abr', 'Abrasi', $c, $surface, '#d97706'],
            ['amf', 'Tambalan amalgam', $r, $surface, '#374151'],
            ['cof', 'Tambalan komposit', $r, $surface, '#2563eb'],
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
