<?php

use App\Console\Commands\BranchReadinessCheck;
use App\Enums\CommissionBase;
use App\Enums\CommissionRecognition;
use App\Enums\CommissionRole;
use App\Enums\CommissionType;
use App\Filament\Pages\BranchReadinessPage;
use App\Filament\Pages\Guide;
use App\Models\Branch;
use App\Models\Chair;
use App\Models\CommissionRule;
use App\Models\Practitioner;
use App\Models\PractitionerSchedule;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Clinic\BranchReadiness;
use Livewire\Livewire;

function readinessStaff(Branch $branch, string $role): User
{
    $user = User::factory()->withMfa()->create();
    $user->assignRole($role);
    $user->branches()->sync([$branch->id]);

    return $user;
}

it('cabang kosong belum siap dan menyebut butir yang kurang', function () {
    config()->set('satusehat.enabled', true);
    $branch = Branch::factory()->create(['code' => 'BRU', 'phone' => null]);

    $checks = collect((new BranchReadiness($branch))->checks())->keyBy('label');

    expect((new BranchReadiness($branch))->ready())->toBeFalse()
        ->and($checks['Data cabang lengkap & aktif'])->ok->toBeFalse()->detail->toContain('telepon')
        ->and($checks['Kursi / dental unit']['ok'])->toBeFalse()
        ->and($checks['Akun Kasir']['ok'])->toBeFalse()
        ->and($checks['SATUSEHAT: Location cabang']['ok'])->toBeFalse()
        ->and($checks['Stok awal (opname disetujui)']['level'])->toBe(BranchReadiness::RECOMMENDED);

    $this->artisan(BranchReadinessCheck::class, ['branch' => 'BRU'])->assertFailed();
    $this->artisan(BranchReadinessCheck::class, ['branch' => 'TIDAKADA'])->assertFailed();
});

it('cabang dengan staf, kursi, jadwal, SIP, tarif, dan aturan jasa siap dipakai', function () {
    config()->set('satusehat.enabled', false);
    $branch = Branch::factory()->create(['code' => 'SIAP']);
    $chair = Chair::create(['branch_id' => $branch->id, 'name' => 'Kursi 1', 'is_active' => true]);

    $doctorUser = readinessStaff($branch, 'dokter');
    $doctor = Practitioner::factory()->create(['user_id' => $doctorUser->id]);
    PractitionerSchedule::create(['practitioner_id' => $doctor->id, 'branch_id' => $branch->id, 'chair_id' => $chair->id, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '15:00', 'is_active' => true]);
    readinessStaff($branch, 'resepsionis');
    readinessStaff($branch, 'kasir');
    Treatment::factory()->create();
    CommissionRule::create(['name' => 'Umum', 'role' => CommissionRole::Operator, 'type' => CommissionType::Percent, 'percent' => 40,
        'base' => CommissionBase::Net, 'recognition' => CommissionRecognition::Performed, 'is_active' => true]);

    expect((new BranchReadiness($branch))->ready())->toBeTrue();
    $this->artisan(BranchReadinessCheck::class, ['branch' => 'SIAP'])->expectsOutputToContain('SIAP')->assertSuccessful();

    // SIP lewat → kembali belum siap.
    $doctor->update(['sip_expired_at' => now()->subDay()]);
    expect((new BranchReadiness($branch))->ready())->toBeFalse();

    loginAs('owner');
    Livewire::test(BranchReadinessPage::class)->assertOk()->assertSee('SIAP')->assertSee('SIP dokter terjadwal masih berlaku');
});

it('panduan menampilkan bagian umum + peran pengguna; owner melihat semua', function () {
    $kasir = loginAs('kasir');
    expect(array_keys(Guide::sectionsFor($kasir)))->toBe(['umum', 'kasir']);
    Livewire::test(Guide::class)->assertOk()->assertSee('Tutup kasir')->assertDontSee('Tanda tangani & kunci');

    $owner = loginAs('owner');
    expect(array_keys(Guide::sectionsFor($owner)))->toBe(array_keys(Guide::ROLES));

    foreach (array_keys(Guide::ROLES) as $role) {
        expect(resource_path("guides/{$role}.md"))->toBeFile();
    }
});
