<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\ItemType;
use App\Enums\PayerType;
use App\Enums\PractitionerType;
use App\Enums\RecallStatus;
use App\Enums\TreatmentCategory;
use App\Enums\TreatmentPlanStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Practitioner;
use App\Models\Recall;
/**
 * Data demo untuk lingkungan lokal/staging. JANGAN dijalankan di produksi.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Semua akun demo memakai password dari config('clinic.demo_password') (default "password")
 * dan wajib mengatur MFA saat login pertama.
 */
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Clinic\VisitFlow;
use Illuminate\Database\Seeder;
use RuntimeException;

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
            $this->seedPatients(25, $kemang->id, Payer::where('code', 'UMUM')->value('id'));
        }

        Treatment::where('code', 'SCL')->update(['recall_months' => 6]);
        Treatment::where('code', 'KBH')->update(['recall_months' => 1]);

        $this->seedSchedules($kemang, $depok);

        // Jalankan ulang seeder ini di hari lain untuk mendapat janji temu demo hari itu.
        if (Appointment::whereDate('start_at', today())->doesntExist()) {
            $this->seedDay($kemang);
        }
    }

    private function seedSchedules(Branch $kemang, Branch $depok): void
    {
        $budi = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'dokter@demo.test'))->first();
        $maya = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'dokter2@demo.test'))->first();
        [$kmg1, $kmg2] = [$kemang->chairs()->where('name', 'Unit 1')->value('id'), $kemang->chairs()->where('name', 'Unit 2')->value('id')];
        $dpk1 = $depok->chairs()->where('name', 'Unit 1')->value('id');

        foreach (range(1, 6) as $day) {
            $budi->schedules()->firstOrCreate(['branch_id' => $kemang->id, 'day_of_week' => $day], ['chair_id' => $kmg1, 'start_time' => '08:00', 'end_time' => '16:00']);
        }

        foreach ([1, 3, 4, 6] as $day) {
            $maya->schedules()->firstOrCreate(['branch_id' => $kemang->id, 'day_of_week' => $day], ['chair_id' => $kmg2, 'start_time' => '08:00', 'end_time' => '17:00']);
        }

        foreach ([2, 5] as $day) {
            $maya->schedules()->firstOrCreate(['branch_id' => $depok->id, 'day_of_week' => $day], ['chair_id' => $dpk1, 'start_time' => '09:00', 'end_time' => '17:00']);
        }
    }

    /** Satu hari praktik di Kemang: janji temu per kursi, satu pasien di ruang tunggu, rencana & recall. */
    private function seedDay(Branch $kemang): void
    {
        $budi = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'dokter@demo.test'))->first();
        $maya = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'dokter2@demo.test'))->first();
        $chairs = $kemang->chairs()->orderBy('name')->pluck('id')->all();
        $patients = Patient::orderBy('id')->limit(12)->get();

        $day = [
            // [pasien, dokter, kursi, jam, menit, kategori, rencana, status]
            [0, $budi, 0, '08:30', 30, TreatmentCategory::Preventive, 'Scaling RA & RB', AppointmentStatus::Confirmed],
            [1, $budi, 0, '09:00', 45, TreatmentCategory::Restorative, 'Tambal komposit 36 O, 37 MO', AppointmentStatus::Confirmed],
            [2, $budi, 0, '10:00', 45, TreatmentCategory::Consultation, 'Konsultasi gigi berlubang', AppointmentStatus::Booked],
            [3, $budi, 0, '13:00', 90, TreatmentCategory::Surgery, 'Odontektomi 38', AppointmentStatus::Confirmed],
            [4, $budi, 0, '15:00', 30, TreatmentCategory::Surgery, 'Cabut gigi susu 85', AppointmentStatus::Booked],
            [5, $maya, 1, '08:30', 30, TreatmentCategory::Orthodontic, 'Kontrol behel', AppointmentStatus::Confirmed],
            [6, $maya, 1, '09:30', 30, TreatmentCategory::Orthodontic, 'Kontrol behel', AppointmentStatus::Confirmed],
            [7, $maya, 1, '11:00', 30, TreatmentCategory::Consultation, 'Konsultasi ortodonti', AppointmentStatus::Booked],
            [8, $maya, 1, '13:30', 90, TreatmentCategory::Orthodontic, 'Pasang behel metal RA + RB', AppointmentStatus::Confirmed],
        ];

        foreach ($day as [$p, $doctor, $chair, $time, $minutes, $category, $reason, $status]) {
            $start = today()->setTimeFromTimeString($time);
            Appointment::create([
                'branch_id' => $kemang->id,
                'patient_id' => $patients[$p]->id,
                'practitioner_id' => $doctor->id,
                'chair_id' => $chairs[$chair] ?? null,
                'start_at' => $start,
                'end_at' => $start->copy()->addMinutes($minutes),
                'category' => $category,
                'reason' => $reason,
                'status' => $status,
                'confirmed_at' => $status === AppointmentStatus::Confirmed ? now() : null,
            ]);
        }

        // Pasien jam 09.00 sudah datang dan menunggu.
        $arrived = Appointment::whereDate('start_at', today())->where('patient_id', $patients[1]->id)->first();
        VisitFlow::checkIn($arrived);

        if (TreatmentPlan::where('patient_id', $patients[1]->id)->doesntExist()) {
            $plan = TreatmentPlan::create([
                'patient_id' => $patients[1]->id,
                'practitioner_id' => $budi->id,
                'branch_id' => $kemang->id,
                'title' => 'Rencana perawatan menyeluruh',
                'status' => TreatmentPlanStatus::Accepted,
                'presented_at' => now()->subWeek(),
                'decided_at' => now()->subWeek(),
            ]);
            $tko = Treatment::where('code', 'TKO')->first();
            $cbd = Treatment::where('code', 'CBD')->first();
            $plan->items()->create(['treatment_id' => $tko->id, 'tooth_number' => '36', 'surfaces' => 'O', 'phase' => 1, 'sequence' => 1, 'price_estimate' => 400_000]);
            $plan->items()->create(['treatment_id' => $tko->id, 'tooth_number' => '37', 'surfaces' => 'MO', 'phase' => 1, 'sequence' => 2, 'price_estimate' => 400_000]);
            $plan->items()->create(['treatment_id' => $cbd->id, 'tooth_number' => '18', 'phase' => 2, 'sequence' => 3, 'price_estimate' => 300_000]);
        }

        $scaling = Treatment::where('code', 'SCL')->first();
        foreach ($patients->slice(9, 3)->values() as $i => $patient) {
            Recall::firstOrCreate(['patient_id' => $patient->id, 'treatment_id' => $scaling->id], [
                'branch_id' => $kemang->id,
                'reason' => 'Kontrol: scaling 6 bulan',
                'due_date' => today()->addDays([-20, 3, 12][$i]),
                'status' => RecallStatus::Pending,
            ]);
        }
    }

    /**
     * Pasien fiktif tanpa Faker (Faker hanya ada di dependency development,
     * sedangkan seeder ini juga dipakai di server staging).
     */
    private function seedPatients(int $count, int $branchId, ?int $payerId): void
    {
        mt_srand(2026);

        $male = ['Budi', 'Agus', 'Rizky', 'Dimas', 'Fajar', 'Hendra', 'Joko', 'Yusuf', 'Arif', 'Bayu', 'Rafi', 'Satria'];
        $female = ['Siti', 'Dewi', 'Rina', 'Ayu', 'Putri', 'Nur', 'Fitri', 'Intan', 'Lestari', 'Wulan', 'Citra', 'Anisa'];
        $surnames = ['Santoso', 'Wijaya', 'Saputra', 'Lestari', 'Hidayat', 'Pratama', 'Nugroho', 'Kusuma', 'Siregar', 'Nasution', 'Halim', 'Permata'];
        $cities = ['Jakarta', 'Depok', 'Bogor', 'Bekasi', 'Tangerang', 'Bandung', 'Surabaya', 'Medan'];
        $streets = ['Jl. Kemang Raya', 'Jl. Margonda', 'Jl. Fatmawati', 'Jl. Pahlawan', 'Jl. Merdeka', 'Jl. Sudirman'];
        $allergies = ['Penisilin', 'Amoksisilin', 'Lidokain', 'Lateks', 'Ibuprofen'];
        $diseases = ['Hipertensi', 'Diabetes melitus tipe 2', 'Asma', 'Kelainan jantung'];

        for ($i = 0; $i < $count; $i++) {
            $isMale = mt_rand(0, 1) === 1;
            $first = $isMale ? $male[array_rand($male)] : $female[array_rand($female)];

            Patient::create([
                'name' => $first.' '.$surnames[array_rand($surnames)],
                'nik' => '3171'.str_pad((string) mt_rand(0, 999_999_999_999), 12, '0', STR_PAD_LEFT),
                'birth_place' => $cities[array_rand($cities)],
                'birth_date' => now()->subDays(mt_rand(3 * 365, 75 * 365))->toDateString(),
                'gender' => $isMale ? 'male' : 'female',
                'phone' => '08'.mt_rand(11, 99).str_pad((string) mt_rand(0, 99_999_999), 8, '0', STR_PAD_LEFT),
                'address' => $streets[array_rand($streets)].' No. '.mt_rand(1, 250).', '.$cities[array_rand($cities)],
                'allergies' => mt_rand(1, 100) <= 15 ? $allergies[array_rand($allergies)] : null,
                'systemic_diseases' => mt_rand(1, 100) <= 20 ? $diseases[array_rand($diseases)] : null,
                'registered_branch_id' => $branchId,
                'default_payer_id' => $payerId,
                'consent_data_at' => now(),
            ]);
        }
    }
}
