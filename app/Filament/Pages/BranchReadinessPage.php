<?php

namespace App\Filament\Pages;

use App\Filament\Support\NavGroup;
use App\Models\Branch;
use App\Services\Clinic\BranchReadiness;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Daftar periksa rollout per cabang (Fase 7): akun staf, kursi, jadwal, SIP, tarif, aturan jasa, stok awal,
 * target, SATUSEHAT. Untuk owner/admin (izin Update:Branch). Versi konsol: `php artisan clinic:branch-readiness`.
 */
class BranchReadinessPage extends Page
{
    protected string $view = 'filament.pages.branch-readiness';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::ORGANIZATION;

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Kesiapan cabang';

    protected static ?string $title = 'Kesiapan cabang';

    protected static ?string $slug = 'kesiapan-cabang';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('Update:Branch') ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Semua butir "Wajib" harus terpenuhi sebelum cabang mulai memakai aplikasi. "Disarankan" boleh menyusul di minggu pertama.';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'branches' => Branch::query()->orderByDesc('is_active')->orderBy('code')->get()->map(function (Branch $branch): array {
                $checks = (new BranchReadiness($branch))->checks();
                $blocking = collect($checks)->filter(fn (array $c): bool => ! $c['ok'] && $c['level'] === BranchReadiness::REQUIRED)->count();

                return ['branch' => $branch, 'checks' => $checks, 'blocking' => $blocking];
            }),
        ];
    }
}
