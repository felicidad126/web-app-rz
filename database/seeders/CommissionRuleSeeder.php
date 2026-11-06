<?php

namespace Database\Seeders;

use App\Enums\CommissionBase;
use App\Enums\CommissionRecognition;
use App\Enums\CommissionRole;
use App\Enums\CommissionType;
use App\Enums\FeeGroup;
use App\Enums\SalaryComponentKind;
use App\Models\CommissionRule;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

/**
 * Aturan jasa awal sesuai usulan yang disampaikan ke owner (review 6 Nov 2026, pertanyaan 1, 2, 5):
 * basis setelah diskon; konsultasi 100%; tindakan umum 40%; tindakan dengan lab & ortodonti 40% dari
 * (setelah diskon − bahan − biaya lab); jasa diakui saat tindakan dikerjakan. Persentase tetap keputusan owner
 * dan bisa diubah di menu Aturan jasa. Hanya diisi bila belum ada aturan sama sekali.
 */
class CommissionRuleSeeder extends Seeder
{
    public function run(): void
    {
        if (CommissionRule::withTrashed()->doesntExist()) {
            $rules = [
                ['Default dokter: 40% setelah diskon', null, 40, CommissionBase::Net],
                ['Konsultasi: 100% setelah diskon', FeeGroup::Consultation, 100, CommissionBase::Net],
                ['Tindakan umum: 40% setelah diskon', FeeGroup::General, 40, CommissionBase::Net],
                ['Tindakan dengan lab: 40% setelah diskon − bahan − lab', FeeGroup::LabWork, 40, CommissionBase::NetMinusMaterialAndLab],
                ['Ortodonti: 40% setelah diskon − bahan − lab', FeeGroup::Orthodontic, 40, CommissionBase::NetMinusMaterialAndLab],
            ];

            foreach ($rules as [$name, $group, $percent, $base]) {
                CommissionRule::create([
                    'name' => $name,
                    'fee_group' => $group,
                    'role' => CommissionRole::Operator,
                    'type' => CommissionType::Percent,
                    'percent' => $percent,
                    'base' => $base,
                    'recognition' => CommissionRecognition::Performed,
                    'notes' => 'Usulan awal (review 6 Nov 2026). Sesuaikan dengan keputusan owner.',
                ]);
            }
        }

        if (SalaryComponent::withTrashed()->doesntExist()) {
            $components = [
                ['Gaji pokok', SalaryComponentKind::Earning],
                ['Uang makan', SalaryComponentKind::Earning],
                ['Tunjangan transport', SalaryComponentKind::Earning],
                ['Lembur', SalaryComponentKind::Earning],
                ['Potongan BPJS Kesehatan', SalaryComponentKind::Deduction],
                ['Potongan BPJS Ketenagakerjaan', SalaryComponentKind::Deduction],
                ['PPh 21 (diisi manual)', SalaryComponentKind::Deduction],
                ['Kasbon', SalaryComponentKind::Deduction],
            ];

            foreach ($components as $sort => [$name, $kind]) {
                SalaryComponent::create(['name' => $name, 'kind' => $kind, 'sort' => $sort + 1]);
            }
        }
    }
}
