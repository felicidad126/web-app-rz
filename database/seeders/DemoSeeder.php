<?php

namespace Database\Seeders;

use App\Enums\ItemType;
use App\Enums\PayerType;
use App\Enums\PractitionerType;
use App\Enums\TreatmentCategory;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Practitioner;
use App\Models\Treatment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo untuk lingkungan lokal/staging. JANGAN dijalankan di produksi.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Semua akun demo memakai password dari config('clinic.demo_password') (default "password")
 * dan wajib mengatur MFA saat login pertama.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di produksi.');
        }

        $this->call(DatabaseSeeder::class);

        $kemang = Branch::firstOrCreate(['code' => 'KMG'], ['name' => 'Senyum Dental — Kemang', 'address' => 'Jl. Kemang Raya No. 10, Jakarta Selatan', 'phone' => '021-7190000']);
        $depok = Branch::firstOrCreate(['code' => 'DPK'], ['name' => 'Senyum Dental — Depok', 'address' => 'Jl. Margonda Raya No. 200, Depok', 'phone' => '021-7770000']);

        foreach ([$kemang, $depok] as $branch) {
            foreach (['Unit 1', 'Unit 2'] as $chair) {
                $branch->chairs()->firstOrCreate(['name' => $chair]);
            }
        }

        $accounts = [
            ['owner@demo.test', 'Drg. Ratna Owner', 'owner', [$kemang, $depok]],
            ['admin@demo.test', 'Sari Admin', 'admin', [$kemang, $depok]],
            ['dokter@demo.test', 'Drg. Budi Santoso', 'dokter', [$kemang]],
            ['dokter2@demo.test', 'Drg. Maya Lestari, Sp.Ort', 'dokter', [$kemang, $depok]],
            ['perawat@demo.test', 'Dewi Perawat', 'perawat', [$kemang]],
            ['resepsionis@demo.test', 'Rina Resepsionis', 'resepsionis', [$kemang]],
            ['kasir@demo.test', 'Andi Kasir', 'kasir', [$kemang]],
        ];

        foreach ($accounts as [$email, $name, $role, $branches]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => config('clinic.demo_password'), 'is_active' => true]);
            $user->syncRoles([$role]);
            $user->branches()->syncWithoutDetaching(collect($branches)->pluck('id'));
        }

        Practitioner::firstOrCreate(['user_id' => User::where('email', 'dokter@demo.test')->value('id')], [
            'type' => PractitionerType::Dentist, 'str_number' => '3172100120001234', 'sip_number' => 'SIP/0123/DG/KMG/2024', 'sip_expired_at' => now()->addDays(40),
        ]);
        Practitioner::firstOrCreate(['user_id' => User::where('email', 'dokter2@demo.test')->value('id')], [
            'type' => PractitionerType::DentalSpecialist, 'specialty' => 'Sp.Ort', 'str_number' => '3172100120005678', 'sip_number' => 'SIP/0456/DGS/KMG/2025', 'sip_expired_at' => now()->addYears(3),
        ]);
        Practitioner::firstOrCreate(['user_id' => User::where('email', 'perawat@demo.test')->value('id')], [
            'type' => PractitionerType::DentalTherapist, 'str_number' => '3172200120009999', 'sip_number' => 'SIPTGM/0789/KMG/2025', 'sip_expired_at' => now()->addYears(2),
        ]);

        Payer::firstOrCreate(['code' => 'BPJS'], ['name' => 'BPJS Kesehatan', 'type' => PayerType::Bpjs, 'is_active' => false]);
        Payer::firstOrCreate(['code' => 'ASR-01'], ['name' => 'Asuransi Sehat Sentosa', 'type' => PayerType::Insurance]);

        $treatments = [
            ['KONS', 'Konsultasi & pemeriksaan', TreatmentCategory::Consultation, '89.31', 100_000, 15],
            ['SCL', 'Scaling (pembersihan karang gigi)', TreatmentCategory::Preventive, '96.54', 350_000, 30],
            ['FIS', 'Fissure sealant per gigi', TreatmentCategory::Preventive, '23.49', 200_000, 20],
            ['TKO', 'Tambal komposit (resin) per gigi', TreatmentCategory::Restorative, '23.2', 400_000, 45],
            ['TGI', 'Tambal GIC per gigi', TreatmentCategory::Restorative, '23.2', 250_000, 30],
            ['PSA', 'Perawatan saluran akar (per kunjungan)', TreatmentCategory::Endodontic, '23.71', 600_000, 60],
            ['CBS', 'Cabut gigi susu', TreatmentCategory::Pediatric, '23.01', 150_000, 20],
            ['CBD', 'Cabut gigi dewasa', TreatmentCategory::Surgery, '23.09', 300_000, 30],
            ['ODT', 'Odontektomi (gigi impaksi)', TreatmentCategory::Surgery, '23.19', 2_500_000, 90],
            ['CRN', 'Crown PFM per gigi', TreatmentCategory::Prosthodontic, '23.41', 2_000_000, 60],
            ['GTL', 'Gigi tiruan lepasan akrilik (per rahang)', TreatmentCategory::Prosthodontic, '99.97', 1_500_000, 45],
            ['BHL', 'Pasang behel metal (per rahang)', TreatmentCategory::Orthodontic, '24.7', 4_000_000, 90],
            ['KBH', 'Kontrol behel', TreatmentCategory::Orthodontic, '24.8', 250_000, 30],
            ['BLC', 'Bleaching in-office', TreatmentCategory::Aesthetic, null, 2_500_000, 60],
            ['RPA', 'Rontgen periapikal', TreatmentCategory::Radiology, '87.12', 100_000, 10],
        ];

        foreach ($treatments as [$code, $name, $category, $icd, $price, $duration]) {
            Treatment::firstOrCreate(['code' => $code], [
                'name' => $name, 'category' => $category, 'icd9cm_code' => $icd, 'base_price' => $price, 'duration_minutes' => $duration,
            ]);
        }

        Treatment::where('code', 'SCL')->first()->branchPrices()->firstOrCreate(['branch_id' => $depok->id], ['price' => 300_000]);

        $unit = fn (string $name): int => Unit::where('name', $name)->value('id');

        $items = [
            ['OBT-AMX500', 'Amoxicillin 500 mg', ItemType::Medicine, 'tablet', 50, 2_000, ['strip' => 10, 'box' => 100]],
            ['OBT-MEF500', 'Asam mefenamat 500 mg', ItemType::Medicine, 'tablet', 50, 1_500, ['strip' => 10, 'box' => 100]],
            ['OBT-IBU400', 'Ibuprofen 400 mg', ItemType::Medicine, 'tablet', 30, 1_500, ['strip' => 10]],
            ['BHN-LIDO', 'Lidokain 2% + epinefrin', ItemType::Material, 'cartridge', 20, null, ['box' => 50]],
            ['BHN-KOMP', 'Resin komposit A2', ItemType::Material, 'tube', 3, null, []],
            ['BHN-GIC', 'Glass ionomer cement', ItemType::Material, 'pack', 2, null, []],
            ['ALT-GLV', 'Sarung tangan nitril M', ItemType::Tool, 'pcs', 200, null, ['box' => 100]],
            ['ALT-MSK', 'Masker medis 3 ply', ItemType::Tool, 'pcs', 100, null, ['box' => 50]],
            ['RTL-SKT', 'Sikat gigi ortodonti', ItemType::Retail, 'pcs', 10, 35_000, []],
        ];

        foreach ($items as [$sku, $name, $type, $base, $min, $price, $conversions]) {
            $item = Item::firstOrCreate(['sku' => $sku], [
                'name' => $name, 'type' => $type, 'base_unit_id' => $unit($base), 'min_stock' => $min, 'sell_price' => $price,
                'track_expiry' => $type !== ItemType::Tool,
            ]);

            foreach ($conversions as $unitName => $factor) {
                $item->units()->firstOrCreate(['unit_id' => $unit($unitName)], ['factor_to_base' => $factor]);
            }
        }

        if (Patient::count() < 25) {
            Patient::factory()->count(25)->create(['registered_branch_id' => $kemang->id, 'default_payer_id' => Payer::where('code', 'UMUM')->value('id')]);
        }
    }
}
