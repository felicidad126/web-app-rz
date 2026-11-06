<?php

namespace App\Services\Clinic;

use App\Enums\StockOpnameStatus;
use App\Filament\Clusters\Stock\Resources\StockOpnames\StockOpnameResource;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\CommissionRules\CommissionRuleResource;
use App\Filament\Resources\Practitioners\PractitionerResource;
use App\Filament\Resources\Targets\TargetResource;
use App\Filament\Resources\Treatments\TreatmentResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Branch;
use App\Models\Chair;
use App\Models\CommissionRule;
use App\Models\ItemBatch;
use App\Models\Practitioner;
use App\Models\PractitionerSchedule;
use App\Models\StockOpname;
use App\Models\Target;
use App\Models\Treatment;
use App\Models\User;
use App\Services\SatuSehat\VisitSync;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Daftar periksa sebelum cabang mulai memakai aplikasi (rollout Fase 7).
 * "Wajib" harus terpenuhi sebelum go-live; "Disarankan" boleh menyusul di minggu pertama.
 */
final class BranchReadiness
{
    public const REQUIRED = 'Wajib';

    public const RECOMMENDED = 'Disarankan';

    /** Peran yang wajib punya minimal satu akun aktif di cabang. */
    private const REQUIRED_ROLES = ['dokter' => 'Dokter', 'resepsionis' => 'Resepsionis', 'kasir' => 'Kasir'];

    public function __construct(private readonly Branch $branch) {}

    /** @return list<array{label: string, level: string, ok: bool, detail: string, url: ?string}> */
    public function checks(): array
    {
        $b = $this->branch;
        $checks = [];

        $missing = collect(['address' => 'alamat', 'phone' => 'telepon'])->filter(fn (string $label, string $field): bool => blank($b->getAttribute($field)));
        $checks[] = $this->check('Data cabang lengkap & aktif', self::REQUIRED, $b->is_active && $missing->isEmpty(),
            $b->is_active ? ($missing->isEmpty() ? 'Alamat & telepon terisi (dipakai di nota dan surat).' : 'Belum diisi: '.$missing->implode(', ').'.') : 'Cabang belum diaktifkan.',
            $this->url(fn () => BranchResource::getUrl('edit', ['record' => $b])));

        $chairs = Chair::where('branch_id', $b->id)->where('is_active', true)->count();
        $checks[] = $this->check('Kursi / dental unit', self::REQUIRED, $chairs > 0, "{$chairs} kursi aktif.",
            $this->url(fn () => BranchResource::getUrl('edit', ['record' => $b])));

        foreach (self::REQUIRED_ROLES as $role => $label) {
            $count = $this->staff()->whereHas('roles', fn (Builder $q) => $q->where('name', $role))->count();
            $checks[] = $this->check("Akun {$label}", self::REQUIRED, $count > 0, "{$count} akun aktif di cabang ini.", $this->url(fn () => UserResource::getUrl()));
        }

        $nurses = $this->staff()->whereHas('roles', fn (Builder $q) => $q->where('name', 'perawat'))->count();
        $checks[] = $this->check('Akun Perawat', self::RECOMMENDED, $nurses > 0, "{$nurses} akun aktif di cabang ini.", $this->url(fn () => UserResource::getUrl()));

        $noMfa = $this->staff()->whereNull('app_authentication_secret')->count();
        $checks[] = $this->check('MFA staf sudah diaktifkan', self::RECOMMENDED, $noMfa === 0,
            $noMfa === 0 ? 'Semua staf sudah mengatur authenticator.' : "{$noMfa} akun belum mengatur MFA (diminta saat login pertama).", null);

        $schedules = PractitionerSchedule::where('branch_id', $b->id)->where('is_active', true);
        $doctorIds = (clone $schedules)->distinct()->pluck('practitioner_id');
        $checks[] = $this->check('Jadwal praktik dokter', self::REQUIRED, $doctorIds->isNotEmpty(),
            $doctorIds->count().' dokter, '.(clone $schedules)->count().' slot jadwal mingguan.', $this->url(fn () => PractitionerResource::getUrl()));

        $sipExpired = Practitioner::whereIn('id', $doctorIds)->where(fn (Builder $q) => $q->whereNull('sip_expired_at')->orWhereDate('sip_expired_at', '<', today()))->count();
        $checks[] = $this->check('SIP dokter terjadwal masih berlaku', self::REQUIRED, $doctorIds->isNotEmpty() && $sipExpired === 0,
            $sipExpired === 0 ? 'Semua dokter terjadwal punya SIP berlaku.' : "{$sipExpired} dokter belum mengisi SIP atau SIP-nya lewat.", $this->url(fn () => PractitionerResource::getUrl()));

        $treatments = Treatment::where('is_active', true)->count();
        $checks[] = $this->check('Tindakan & tarif', self::REQUIRED, $treatments > 0, "{$treatments} tindakan aktif (tarif khusus cabang opsional).",
            $this->url(fn () => TreatmentResource::getUrl()));

        $rules = CommissionRule::where('is_active', true)->count();
        $checks[] = $this->check('Aturan jasa dokter', self::REQUIRED, $rules > 0, "{$rules} aturan aktif.", $this->url(fn () => CommissionRuleResource::getUrl()));

        $opname = StockOpname::where('branch_id', $b->id)->where('status', StockOpnameStatus::Approved)->exists();
        $batches = ItemBatch::where('branch_id', $b->id)->where('qty_on_hand', '>', 0)->count();
        $checks[] = $this->check('Stok awal (opname disetujui)', self::RECOMMENDED, $opname,
            ($opname ? 'Opname awal sudah disetujui. ' : 'Belum ada opname yang disetujui. ')."{$batches} batch berisi stok.", $this->url(fn () => StockOpnameResource::getUrl()));

        $target = Target::where('branch_id', $b->id)->whereNull('practitioner_id')->whereDate('period', today()->startOfMonth())->exists();
        $checks[] = $this->check('Target produksi bulan ini', self::RECOMMENDED, $target,
            $target ? 'Target cabang bulan ini sudah diisi.' : 'Belum ada target; briefing pagi & dashboard tampil tanpa pembanding.', $this->url(fn () => TargetResource::getUrl()));

        if (VisitSync::enabled()) {
            $checks[] = $this->check('SATUSEHAT: Location cabang', self::REQUIRED, filled($b->satusehat_location_id),
                filled($b->satusehat_location_id) ? 'ID Location terisi.' : 'Isi/daftarkan Location di halaman cabang; kunjungan tidak bisa dikirim tanpa ini.',
                $this->url(fn () => BranchResource::getUrl('edit', ['record' => $b])));

            $noIhs = Practitioner::whereIn('id', $doctorIds)->whereNull('satusehat_ihs_id')->count();
            $checks[] = $this->check('SATUSEHAT: IHS dokter', self::REQUIRED, $doctorIds->isNotEmpty() && $noIhs === 0,
                $noIhs === 0 ? 'Semua dokter terjadwal punya ID IHS.' : "{$noIhs} dokter belum punya ID IHS (cari dari NIK di profil nakes).",
                $this->url(fn () => PractitionerResource::getUrl()));
        } else {
            $checks[] = $this->check('SATUSEHAT', self::RECOMMENDED, false, 'Belum diaktifkan (SATUSEHAT_ENABLED di .env). Kunjungan ditahan sampai diaktifkan.', null);
        }

        return $checks;
    }

    public function ready(): bool
    {
        return collect($this->checks())->every(fn (array $c): bool => $c['ok'] || $c['level'] !== self::REQUIRED);
    }

    /** @return Builder<User> */
    private function staff(): Builder
    {
        return User::query()->where('is_active', true)->whereHas('branches', fn (Builder $q) => $q->whereKey($this->branch->id));
    }

    /** @return array{label: string, level: string, ok: bool, detail: string, url: ?string} */
    private function check(string $label, string $level, bool $ok, string $detail, ?string $url): array
    {
        return compact('label', 'level', 'ok', 'detail', 'url');
    }

    /** URL halaman perbaikan di panel (null bila tidak bisa dibentuk). */
    private function url(callable $resolve): ?string
    {
        try {
            return $resolve();
        } catch (Throwable) {
            return null;
        }
    }
}
