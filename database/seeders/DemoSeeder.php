<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\CommissionBase;
use App\Enums\CommissionRecognition;
use App\Enums\CommissionRole;
use App\Enums\CommissionType;
use App\Enums\ItemType;
use App\Enums\LabWorkType;
use App\Enums\OdontogramEntryType;
use App\Enums\PayerType;
use App\Enums\PractitionerType;
use App\Enums\RecallStatus;
use App\Enums\StockReason;
use App\Enums\TreatmentCategory;
use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CommissionPayout;
/**
 * Data demo untuk lingkungan lokal/staging. JANGAN dijalankan di produksi.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Semua akun demo memakai password dari config('clinic.demo_password') (default "password")
 * dan wajib mengatur MFA saat login pertama.
 */
use App\Models\CommissionRule;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\OdontogramEntry;
use App\Models\Package;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\Payer;
use App\Models\Payslip;
use App\Models\Practitioner;
use App\Models\PurchaseRequest;
use App\Models\Recall;
use App\Models\SalaryComponent;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\Target;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\CashSessions;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PackageSales;
use App\Services\Billing\PaymentRecorder;
use App\Services\Clinic\VisitFlow;
use App\Services\Commission\Attendances;
use App\Services\Commission\CommissionPayouts;
use App\Services\Lab\LabOrders;
use App\Services\Payroll\Payslips;
use App\Services\Purchasing\Purchasing;
use App\Services\Reports\DailyMetrics;
use App\Services\Reports\Targets;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockOpnames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
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

        $this->seedBilling($kemang);
        $this->seedStock($kemang, $depok);
        $this->seedPurchasing($kemang);
        $this->seedPerformance($kemang, $depok);
    }

    /**
     * Fase 6: riwayat ±4 minggu (kunjungan selesai, tagihan, pembayaran, sebagian belum lunas, tidak datang),
     * rencana perawatan, target, uang hadir, aturan jasa asisten, rekap jasa & slip gaji bulan lalu, lalu snapshot KPI.
     * Dibuat sekali (penanda: kunjungan selesai dalam 28 hari terakhir); urutan acak tetap (seed 2026) agar angka stabil.
     */
    private function seedPerformance(Branch $kemang, Branch $depok): void
    {
        $owner = User::where('email', 'owner@demo.test')->firstOrFail();
        $kasir = User::where('email', 'kasir@demo.test')->firstOrFail();
        $budi = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'dokter@demo.test'))->firstOrFail();
        $maya = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'dokter2@demo.test'))->firstOrFail();
        $dewi = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'perawat@demo.test'))->firstOrFail();

        $budi->update(['attendance_fee' => 150_000, 'on_call_fee' => 75_000]);
        $maya->update(['attendance_fee' => 200_000, 'on_call_fee' => 100_000]);

        CommissionRule::firstOrCreate(['name' => 'Asisten: Rp 10.000 per tindakan'], [
            'role' => CommissionRole::Assistant, 'type' => CommissionType::Fixed, 'fixed_amount' => 10_000,
            'base' => CommissionBase::Net, 'recognition' => CommissionRecognition::Performed,
            'notes' => 'Contoh dari mockup (insentif staf non-dokter). Sesuaikan dengan keputusan owner.',
        ]);

        // Target produksi per hari kerja, sebanding dengan volume data demo (mockup memakai skala klinik penuh).
        foreach ([now()->startOfMonth()->subMonthNoOverflow(), now()->startOfMonth()] as $month) {
            foreach ([[$kemang, 1_600_000], [$depok, 600_000]] as [$branch, $daily]) {
                Target::firstOrCreate(
                    ['period' => $month->toDateString(), 'branch_id' => $branch->id, 'practitioner_id' => null, 'metric' => 'production'],
                    ['amount' => $daily * max(1, Targets::workingDays($month, $branch->id)->count())],
                );
            }
        }

        $start = today()->subDays(28);
        $end = today(); // tetap, karena setTestNow di dalam loop menggeser today()
        $hasHistory = Visit::query()->whereIn('status', [VisitStatus::Done, VisitStatus::Billed])
            ->whereDate('visit_date', '>=', $start)->whereDate('visit_date', '<', today()->subDays(2))->count() > 5;

        if (! $hasHistory) {
            mt_srand(2026);

            // Restok sebelum riwayat agar pemakaian BOM tidak membuat stok minus.
            Carbon::setTestNow($start->copy()->setTime(9, 0));
            $box = Unit::where('name', 'box')->value('id');
            $komp = Item::where('sku', 'BHN-KOMP')->firstOrFail();
            StockLedger::receive($kemang->id, [
                ['item_id' => $komp->id, 'unit_id' => $komp->base_unit_id, 'qty' => 6, 'unit_price' => 285_000, 'batch_number' => 'KMP-2210', 'expired_at' => today()->addYear()->endOfMonth()->toDateString()],
                ['item_id' => Item::where('sku', 'ALT-GLV')->value('id'), 'unit_id' => $box, 'qty' => 3, 'unit_price' => 65_000, 'batch_number' => 'NTR-M-02', 'expired_at' => null],
                ['item_id' => Item::where('sku', 'BHN-LIDO')->value('id'), 'unit_id' => $box, 'qty' => 1, 'unit_price' => 490_000, 'batch_number' => 'LD-0801', 'expired_at' => today()->addMonths(15)->endOfMonth()->toDateString()],
                ['item_id' => Item::where('sku', 'BHN-GIC')->value('id'), 'unit_id' => Item::where('sku', 'BHN-GIC')->value('base_unit_id'), 'qty' => 2, 'unit_price' => 420_000, 'batch_number' => 'GIC-2210', 'expired_at' => today()->addMonths(14)->endOfMonth()->toDateString()],
            ], Supplier::where('name', 'PT Medika Jaya')->value('id'), 'MJ/RESTOK/01', today(), null, User::where('email', 'perawat@demo.test')->firstOrFail());
            $patients = Patient::orderBy('id')->get();
            $treatments = Treatment::whereIn('code', ['KONS', 'SCL', 'TKO', 'TGI', 'CBD', 'PSA', 'RPA', 'CBS'])->get()->keyBy('code');
            $menu = [['KONS'], ['KONS', 'SCL'], ['TKO'], ['TKO', 'RPA'], ['TGI'], ['CBD'], ['PSA'], ['SCL'], ['KONS', 'TKO'], ['CBS']];
            $methods = ['cash', 'qris', 'transfer', 'debit', 'cash'];

            for ($day = $start->copy(); $day->lt($end); $day->addDay()) {
                foreach ([[$kemang, [$budi, $maya], [3, 6]], [$depok, [$maya], [1, 3]]] as [$branch, $doctors, [$min, $max]]) {
                    $working = collect($doctors)->filter(fn (Practitioner $d): bool => array_sum(DailyMetrics::scheduledMinutes($day, $branch->id, $d->id)) > 0)->values();

                    if ($working->isEmpty()) {
                        continue;
                    }

                    Carbon::setTestNow($day->copy()->setTime(8, 0));
                    $session = CashSessions::current($kasir, $branch->id) ?? CashSessions::open($kasir, $branch->id, 500_000);
                    $count = mt_rand($min, $max);

                    for ($i = 0; $i < $count; $i++) {
                        $doctor = $working[$i % $working->count()];
                        $patient = $patients[mt_rand(0, $patients->count() - 1)];
                        $arrived = $day->copy()->setTime(8 + $i, mt_rand(0, 40));
                        $started = $arrived->copy()->addMinutes(mt_rand(5, 25));
                        Carbon::setTestNow($started);

                        $appointment = Appointment::create([
                            'branch_id' => $branch->id, 'patient_id' => $patient->id, 'practitioner_id' => $doctor->id,
                            'start_at' => $arrived->copy()->setMinute(0), 'end_at' => $arrived->copy()->setMinute(0)->addMinutes(45),
                            'reason' => 'Kontrol / perawatan', 'status' => AppointmentStatus::Arrived,
                        ]);
                        $visit = Visit::create([
                            'appointment_id' => $appointment->id,
                            'branch_id' => $branch->id, 'patient_id' => $patient->id, 'practitioner_id' => $doctor->id,
                            'visit_date' => $day->toDateString(), 'status' => VisitStatus::Done, 'arrived_at' => $arrived, 'started_at' => $started,
                            'finished_at' => $started->copy()->addMinutes(mt_rand(20, 60)),
                        ]);

                        foreach ($menu[mt_rand(0, count($menu) - 1)] as $code) {
                            $visit->procedures()->create([
                                'treatment_id' => $treatments[$code]->id, 'operator_id' => $doctor->id,
                                'assistant_id' => mt_rand(0, 2) === 0 ? $dewi->id : null,
                                'tooth_number' => $code === 'KONS' || $code === 'SCL' ? null : (string) [16, 26, 36, 46, 37, 47][mt_rand(0, 5)],
                            ]);
                        }

                        // Depok belum opname di demo, jadi stoknya belum dilacak.
                        if ($branch->is($kemang)) {
                            StockLedger::usageForVisit($visit->fresh(), $kasir);
                        }
                        Carbon::setTestNow($started->copy()->addHour());
                        $invoice = InvoiceBuilder::forVisit($visit->fresh());

                        // Sebagian pasien memakai promo klinik (Inova Care) 10%.
                        if (mt_rand(0, 6) === 0) {
                            $invoice->update(['invoice_discount_percent' => 10]);
                            InvoiceBuilder::recalculate($invoice->fresh());
                        }

                        $invoice = InvoiceBuilder::issue($invoice->fresh(), $kasir);

                        if (mt_rand(0, 9) > 0) {
                            PaymentRecorder::receive($patient, [$invoice->id], [['method' => $methods[mt_rand(0, 4)], 'amount' => $invoice->patient_portion]], $session, $kasir);
                        }
                    }

                    // Satu janji tidak datang tiap beberapa hari.
                    if (mt_rand(0, 3) === 0) {
                        $at = $day->copy()->setTime(15, 0);
                        Appointment::create([
                            'branch_id' => $branch->id, 'patient_id' => $patients[mt_rand(0, $patients->count() - 1)]->id, 'practitioner_id' => $working->first()->id,
                            'start_at' => $at, 'end_at' => $at->copy()->addMinutes(30), 'reason' => 'Kontrol', 'status' => AppointmentStatus::NoShow,
                        ]);
                    }

                    Carbon::setTestNow($day->copy()->setTime(18, 0));
                    $cash = CashSessions::expectedCash($session);
                    $reported = array_diff_key(CashSessions::systemTotals($session), ['cash' => 0]);
                    CashSessions::close($session, $kasir, ['denominations' => ['coins' => $cash], 'reported' => $reported, 'next_opening_cash' => 500_000]);
                    Attendances::fillFromVisits($branch->id, $day->copy(), $owner);
                }
            }

            // Rencana perawatan: diajukan, disetujui, sebagian belum dijadwalkan.
            $filling = Treatment::where('code', 'TKO')->firstOrFail();
            $crown = Treatment::where('code', 'CRN')->firstOrFail();
            foreach ([[10, TreatmentPlanStatus::Accepted, 'Tambal 14, 15', [[$filling, 400_000], [$filling, 400_000]]],
                [11, TreatmentPlanStatus::Accepted, 'Crown PFM 46', [[$crown, 2_000_000]]],
                [12, TreatmentPlanStatus::Rejected, 'Crown PFM 36', [[$crown, 2_000_000]]],
                [13, TreatmentPlanStatus::Presented, 'Tambal 24', [[$filling, 400_000]]]] as [$index, $status, $title, $items]) {
                $presented = today()->subDays(1 + $index % 4)->setTime(11, 0);
                $plan = TreatmentPlan::create([
                    'patient_id' => $patients[$index]->id, 'practitioner_id' => $budi->id, 'branch_id' => $kemang->id, 'title' => $title, 'status' => $status,
                    'presented_at' => $presented, 'decided_at' => $status === TreatmentPlanStatus::Presented ? null : $presented->copy()->addMinutes(30),
                ]);

                foreach ($items as $sequence => [$treatment, $price]) {
                    $plan->items()->create(['treatment_id' => $treatment->id, 'phase' => 1, 'sequence' => $sequence + 1, 'price_estimate' => $price, 'status' => TreatmentPlanItemStatus::Planned]);
                }
            }

            Carbon::setTestNow();

            // Rekap jasa bulan lalu: dibuat & difinalkan owner (bulan berjalan dibiarkan untuk dicoba).
            if (today()->day > 1 && CommissionPayout::query()->doesntExist()) {
                foreach (CommissionPayouts::generate(now()->startOfMonth()->subMonthNoOverflow(), $owner) as $payout) {
                    CommissionPayouts::finalize($payout, $owner);
                }
            }

            // Snapshot KPI untuk hari yang sudah lewat (dashboard & tren).
            for ($day = $start->copy(); $day->lt($end); $day->addDay()) {
                DailyMetrics::snapshot($day->copy());
            }
        }

        // Gaji staf: komponen tetap & slip bulan lalu.
        $components = SalaryComponent::pluck('id', 'name');
        $salaries = [
            'admin@demo.test' => [5_000_000, 600_000, 400_000],
            'perawat@demo.test' => [4_500_000, 600_000, 300_000],
            'resepsionis@demo.test' => [3_800_000, 600_000, 300_000],
            'kasir@demo.test' => [3_800_000, 600_000, 300_000],
        ];

        foreach ($salaries as $email => [$basic, $meal, $transport]) {
            $user = User::where('email', $email)->firstOrFail();

            foreach ([['Gaji pokok', $basic], ['Uang makan', $meal], ['Tunjangan transport', $transport], ['Potongan BPJS Kesehatan', (int) round($basic * 0.01)]] as [$name, $amount]) {
                $user->salaryItems()->firstOrCreate(['salary_component_id' => $components[$name]], ['amount' => $amount]);
            }
        }

        if (Payslip::query()->doesntExist()) {
            foreach (Payslips::generate(now()->startOfMonth()->subMonthNoOverflow(), $owner) as $payslip) {
                Payslips::finalize($payslip, $owner);
            }
        }
    }

    /**
     * Fase 5: kategori, supplier & lab, BOM tindakan, opname awal Kemang (saldo awal) yang sudah disetujui,
     * satu penerimaan, satu transfer ke Depok, satu pemusnahan menunggu persetujuan, dan order lab berjalan.
     * Ada batch yang hampir kedaluwarsa, sudah kedaluwarsa, dan barang di bawah stok minimum agar peringatan terlihat.
     */
    private function seedStock(Branch $kemang, Branch $depok): void
    {
        $categories = [
            'Obat' => ['OBT-AMX500', 'OBT-MEF500', 'OBT-IBU400'],
            'Anestesi' => ['BHN-LIDO'],
            'Bahan restorasi' => ['BHN-KOMP', 'BHN-GIC'],
            'Habis pakai' => ['ALT-GLV', 'ALT-MSK'],
            'Retail' => ['RTL-SKT'],
        ];

        foreach ($categories as $name => $skus) {
            $category = ItemCategory::firstOrCreate(['name' => $name]);
            Item::whereIn('sku', $skus)->whereNull('item_category_id')->update(['item_category_id' => $category->id]);
        }

        $medika = Supplier::firstOrCreate(['name' => 'PT Medika Jaya'], ['contact_person' => 'Pak Hendra', 'phone' => '021-5550101']);
        Supplier::firstOrCreate(['name' => 'CV Dental Supply'], ['contact_person' => 'Bu Lina', 'phone' => '021-5550202']);
        $prima = Supplier::firstOrCreate(['name' => 'Lab Prima Dental'], ['is_lab' => true, 'phone' => '0812-0000-1111']);
        $ceramic = Supplier::firstOrCreate(['name' => 'Lab Senyum Ceramic'], ['is_lab' => true, 'phone' => '0812-0000-2222']);

        $item = fn (string $sku): Item => Item::where('sku', $sku)->firstOrFail();
        $bom = [
            'TKO' => [['BHN-KOMP', 0.2], ['BHN-LIDO', 1], ['ALT-GLV', 2], ['ALT-MSK', 1]],
            'TGI' => [['BHN-GIC', 0.1], ['ALT-GLV', 2], ['ALT-MSK', 1]],
            'CBD' => [['BHN-LIDO', 2], ['ALT-GLV', 2], ['ALT-MSK', 1]],
            'CBS' => [['BHN-LIDO', 1], ['ALT-GLV', 2], ['ALT-MSK', 1]],
            'SCL' => [['ALT-GLV', 2], ['ALT-MSK', 1]],
            'CRN' => [['BHN-LIDO', 1], ['ALT-GLV', 2], ['ALT-MSK', 1]],
        ];

        foreach ($bom as $code => $materials) {
            $treatment = Treatment::where('code', $code)->firstOrFail();

            foreach ($materials as [$sku, $qty]) {
                $material = $item($sku);
                $treatment->materials()->firstOrCreate(['item_id' => $material->id], ['qty' => $qty, 'unit_id' => $material->base_unit_id]);
            }
        }

        if (StockOpname::where('branch_id', $kemang->id)->exists()) {
            return;
        }

        $perawat = User::where('email', 'perawat@demo.test')->firstOrFail();
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();
        // Semua waktu mundur dihitung dari tanggal acuan yang sama (setTestNow menggeser today()).
        $today = today();
        $ed = fn (int $days): string => $today->copy()->addDays($days)->endOfMonth()->toDateString();

        // Opname awal 30 hari lalu = saldo awal sistem. [jumlah, harga pokok, batch, ED]
        $counts = [
            'OBT-AMX500' => [180, 1_200, 'AMX-2409', $ed(240)],
            'OBT-MEF500' => [40, 900, 'MEF-2511', $ed(420)],           // di bawah minimum (50)
            'OBT-IBU400' => [60, 1_000, 'IBU-2601', $today->copy()->addDays(20)->toDateString()], // hampir kedaluwarsa
            'BHN-LIDO' => [35, 9_800, 'LD-0612', $today->copy()->addDays(45)->toDateString()],   // hampir kedaluwarsa
            'BHN-KOMP' => [4, 285_000, 'KMP-2208', $ed(330)],
            'BHN-GIC' => [1, 420_000, 'GIC-2105', $today->copy()->subDays(10)->toDateString()],  // sudah kedaluwarsa
            'ALT-GLV' => [600, 650, 'NTR-M-01', null],
            'ALT-MSK' => [300, 450, 'MSK-01', null],
            'RTL-SKT' => [12, 18_000, 'SKT-01', null],
        ];

        Carbon::setTestNow($today->copy()->subDays(30)->setTime(17, 0));
        $opname = StockOpnames::start($kemang->id, $perawat, 'Opname awal (saldo awal sistem)');

        foreach ($opname->lines()->with('item')->get() as $line) {
            [$qty, $cost, $batch, $expiry] = $counts[$line->item->sku] ?? [0, 0, null, null];
            $line->update(['counted_qty' => $qty, 'unit_cost' => $cost, 'batch_number' => $batch, 'expired_at' => $expiry]);
        }

        StockOpnames::submit($opname, $perawat);
        Carbon::setTestNow($today->copy()->subDays(29)->setTime(8, 30));
        StockOpnames::approve($opname->refresh(), $admin);

        // Penerimaan dari supplier seminggu lalu.
        Carbon::setTestNow($today->copy()->subDays(7)->setTime(11, 0));
        StockLedger::receive($kemang->id, [
            ['item_id' => $item('BHN-LIDO')->id, 'unit_id' => Unit::where('name', 'box')->value('id'), 'qty' => 1, 'unit_price' => 490_000, 'batch_number' => 'LD-0711', 'expired_at' => $ed(420)],
            ['item_id' => $item('ALT-MSK')->id, 'unit_id' => Unit::where('name', 'box')->value('id'), 'qty' => 2, 'unit_price' => 25_000, 'batch_number' => 'MSK-02', 'expired_at' => null],
        ], $medika->id, 'MJ/2611/0457', today(), null, $perawat);

        // Kirim sarung tangan ke Depok.
        Carbon::setTestNow($today->copy()->subDays(5)->setTime(14, 0));
        StockLedger::transfer($kemang->id, $depok->id, [
            ['item_id' => $item('ALT-GLV')->id, 'unit_id' => $item('ALT-GLV')->base_unit_id, 'qty' => 100],
        ], 'Stok Depok menipis', $admin);

        // Pemusnahan GIC kedaluwarsa: diajukan perawat, menunggu persetujuan admin klinik.
        Carbon::setTestNow($today->copy()->subDay()->setTime(16, 0));
        $gic = ItemBatch::where('branch_id', $kemang->id)->where('batch_number', 'GIC-2105')->firstOrFail();
        StockLedger::requestDisposal($kemang->id, [['item_batch_id' => $gic->id, 'qty' => (float) $gic->qty_on_hand]], StockReason::Expired, 'Dimusnahkan bersama limbah B3 klinik, saksi: Dewi Perawat.', $perawat);
        Carbon::setTestNow();

        // Order lab berjalan: satu terlambat, satu jatuh tempo minggu ini.
        $maya = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'dokter2@demo.test'))->firstOrFail();
        $budi = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'dokter@demo.test'))->firstOrFail();
        $patients = Patient::orderBy('id')->limit(12)->get();
        $orders = [
            [$patients[3], $budi, $prima, LabWorkType::Crown, 'PFM, margin chamfer, kontak proksimal rapat', '36', 'A2', 650_000, 9, 2, 1],
            [$patients[7], $maya, $ceramic, LabWorkType::Retainer, 'Hawley RA, klamer Adams 16 26', null, null, 400_000, 10, -2, 1],
            [$patients[9], $budi, $prima, LabWorkType::Denture, 'GTL akrilik RB, elemen 34–37 dan 44–47', '34–37, 44–47', 'A3', 900_000, 4, 5, 0],
        ];

        foreach ($orders as [$patient, $doctor, $lab, $type, $spec, $teeth, $shade, $cost, $daysAgo, $dueIn, $steps]) {
            Carbon::setTestNow($today->copy()->subDays($daysAgo)->setTime(13, 0));
            $order = LabOrders::create([
                'branch_id' => $kemang->id, 'patient_id' => $patient->id, 'practitioner_id' => $doctor->id, 'supplier_id' => $lab->id,
                'work_type' => $type, 'specification' => $spec, 'teeth' => $teeth, 'shade' => $shade, 'cost' => $cost,
                'due_at' => $today->copy()->addDays($dueIn)->toDateString(),
            ], $perawat);

            for ($i = 0; $i < $steps; $i++) {
                Carbon::setTestNow(now()->addDay());
                LabOrders::advance($order, $perawat);
            }
        }

        Carbon::setTestNow();
    }

    /**
     * Pembelian (alur aplikasi inventori klinik): kas pembelian, permintaan perawat (menunggu, disetujui),
     * pesanan yang sudah dibayar dan baru diterima sebagian (lewat perkiraan datang), dan ongkos kirim.
     */
    private function seedPurchasing(Branch $kemang): void
    {
        if (PurchaseRequest::where('branch_id', $kemang->id)->exists()) {
            return;
        }

        $owner = User::where('email', 'owner@demo.test')->firstOrFail();
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();
        $perawat = User::where('email', 'perawat@demo.test')->firstOrFail();
        $item = fn (string $sku): Item => Item::where('sku', $sku)->firstOrFail();
        $box = (int) Unit::where('name', 'box')->value('id');
        $supply = Supplier::where('name', 'CV Dental Supply')->firstOrFail();
        $today = today();
        $at = fn (int $daysAgo, int $hour) => Carbon::setTestNow($today->copy()->subDays($daysAgo)->setTime($hour, 0));

        $at(10, 9);
        Purchasing::deposit($kemang->id, 2_000_000, 'Setoran awal kas pembelian dari owner', $owner);

        $at(9, 10);
        $mef = Purchasing::requestItem($kemang->id, $item('OBT-MEF500')->id, 2, $box, 'Stok 40 tablet, di bawah minimum 50', $perawat);
        $at(9, 13);
        Purchasing::approve($mef, $admin);
        $order = Purchasing::createOrder($kemang->id, $supply->id, today(), [
            ['item_id' => $item('OBT-MEF500')->id, 'unit_id' => $box, 'qty' => 2, 'unit_price' => 85_000, 'purchase_request_id' => $mef->id],
            ['item_id' => $item('OBT-AMX500')->id, 'unit_id' => $box, 'qty' => 1, 'unit_price' => 110_000],
        ], $admin, $today->copy()->subDays(3), 'Kirim ke klinik Kemang', payNow: true);

        $at(6, 11);
        $lines = $order->lines()->get();
        Purchasing::receive($order, [
            ['line_id' => $lines[0]->id, 'qty' => 1, 'batch_number' => 'MEF-2611', 'expired_at' => $today->copy()->addMonths(18)->endOfMonth()->toDateString()],
            ['line_id' => $lines[1]->id, 'qty' => 1, 'batch_number' => 'AMX-2611', 'expired_at' => $today->copy()->addMonths(20)->endOfMonth()->toDateString()],
        ], $perawat, today(), 'CVDS/2611/112');
        Purchasing::expense($kemang->id, 20_000, 'Ongkos kirim CV Dental Supply', $admin);

        $at(2, 15);
        $brush = Purchasing::requestItem($kemang->id, $item('RTL-SKT')->id, 24, $item('RTL-SKT')->base_unit_id, 'Laku untuk pasien behel, stok tinggal 12', $perawat);
        $at(2, 17);
        Purchasing::approve($brush, $owner);

        $at(1, 16);
        Purchasing::requestItem($kemang->id, $item('BHN-KOMP')->id, 2, $item('BHN-KOMP')->base_unit_id, 'Persiapan 3 kasus estetik minggu depan', $perawat);

        Carbon::setTestNow();
    }

    /**
     * Fase 3: paket behel dengan cicilan berjalan, dan satu tagihan lama yang belum lunas
     * (muncul sebagai "saldo terutang" di kasir). Transaksi lama dibuat dengan waktu mundur.
     */
    private function seedBilling(Branch $kemang): void
    {
        $control = Treatment::where('code', 'KBH')->firstOrFail();
        $package = Package::firstOrCreate(['code' => 'ORT-MTL'], [
            'name' => 'Behel metal RA + RB',
            'price' => 8_000_000,
            'validity_days' => 730,
            'description' => 'Termasuk pasang behel dan 12 kali kontrol. Tidak termasuk retainer setelah lepas behel.',
        ]);
        $package->items()->firstOrCreate(['treatment_id' => $control->id], ['quota' => 12]);

        $kasir = User::where('email', 'kasir@demo.test')->firstOrFail();

        // Riwayat transaksi dibuat dengan sesi kasir mundur; jangan ganggu sesi kasir yang sedang dipakai.
        if (CashSessions::current($kasir, $kemang->id) !== null) {
            return;
        }
        $maya = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'dokter2@demo.test'))->firstOrFail();
        $budi = Practitioner::whereHas('user', fn ($q) => $q->where('email', 'dokter@demo.test'))->firstOrFail();
        $patients = Patient::orderBy('id')->limit(12)->get();
        $pay = function (Carbon $at, callable $makeInvoices, string $method, int $amount) use ($kasir, $kemang): void {
            Carbon::setTestNow($at);
            $session = CashSessions::open($kasir, $kemang->id, 500_000);
            $invoices = $makeInvoices();
            PaymentRecorder::receive($invoices[0]->patient, array_map(fn ($i) => $i->id, $invoices), [['method' => $method, 'amount' => $amount]], $session, $kasir);
            $cash = CashSessions::expectedCash($session);
            $reported = array_map(fn (int $v) => $v, array_diff_key(CashSessions::systemTotals($session), ['cash' => 0]));
            CashSessions::close($session, $kasir, ['denominations' => ['coins' => $cash], 'reported' => $reported, 'next_opening_cash' => 500_000]);
            Carbon::setTestNow();
        };

        // Paket behel pasien ortho (kontrol jam 08.30): DP + 6 cicilan, sudah dibayar sampai bulan lalu.
        $orthoPatient = $patients[5];

        if (PatientPackage::where('patient_id', $orthoPatient->id)->doesntExist()) {
            $start = today()->subMonths(5)->startOfMonth()->addDays(9);
            Carbon::setTestNow($start->copy()->setTime(10, 0));
            $sold = PackageSales::sell($orthoPatient, $package, [
                'branch_id' => $kemang->id, 'practitioner_id' => $maya->id, 'down_payment' => 2_000_000,
                'installment_count' => 6, 'start_date' => $start, 'due_day' => 5,
            ]);
            Carbon::setTestNow();

            foreach ($sold->installments()->get() as $installment) {
                if ($installment->due_date->gte(today()->startOfMonth())) {
                    break;
                }

                $paidAt = $installment->due_date->copy()->addDays([0, -2, 2, -1, 1][$installment->sequence % 5])->setTime(11, 0);
                $pay($paidAt, fn () => [InvoiceBuilder::forInstallment($installment, $kasir)], ['transfer', 'qris', 'cash'][$installment->sequence % 3], $installment->amount);
            }
        }

        // Tagihan bulan lalu pasien jam 09.00 yang baru dibayar sebagian (saldo terutang Rp450.000).
        $patient = $patients[1];

        if (Visit::where('patient_id', $patient->id)->whereDate('visit_date', '<', today())->doesntExist()) {
            $at = today()->subMonth()->setTime(10, 0);
            Carbon::setTestNow($at);
            $visit = Visit::create([
                'branch_id' => $kemang->id, 'patient_id' => $patient->id, 'practitioner_id' => $budi->id,
                'visit_date' => $at, 'status' => VisitStatus::Done, 'started_at' => $at, 'finished_at' => $at->copy()->addHour(),
            ]);

            foreach (['KONS', 'PSA', 'TGI'] as $code) {
                $visit->procedures()->create(['treatment_id' => Treatment::where('code', $code)->value('id'), 'operator_id' => $budi->id, 'tooth_number' => $code === 'KONS' ? null : '46']);
            }

            Carbon::setTestNow();
            $pay($at->copy()->addHours(2), fn () => [InvoiceBuilder::issue(InvoiceBuilder::forVisit($visit), $kasir)], 'cash', 500_000);
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

        // Odontogram kondisi awal (tanpa kunjungan) untuk pasien yang sedang menunggu, agar odontogram visual terisi.
        if (OdontogramEntry::where('patient_id', $patients[1]->id)->doesntExist()) {
            $existing = OdontogramEntryType::Existing;
            $planned = OdontogramEntryType::Planned;

            foreach ([
                ['16', 'O', 'amf', $existing], ['46', null, 'mis', $existing], ['26', null, 'poc', $existing], ['11', 'V', 'cof', $existing],
                ['36', 'O', 'car', $existing], ['37', 'M', 'car', $existing], ['37', 'O', 'car', $existing], ['18', null, 'pre', $existing],
                ['36', 'O', 'cof', $planned], ['37', 'M', 'cof', $planned], ['37', 'O', 'cof', $planned], ['18', null, 'mis', $planned],
            ] as [$tooth, $surface, $code, $type]) {
                OdontogramEntry::create(['patient_id' => $patients[1]->id, 'tooth_number' => $tooth, 'surface' => $surface, 'condition_code' => $code, 'entry_type' => $type]);
            }
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
