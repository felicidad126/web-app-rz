<?php

namespace App\Filament\Widgets;

use App\Enums\SatuSehatSyncStatus;
use App\Filament\Pages\SatuSehatMonitor;
use App\Models\SatuSehatSync;
use App\Services\SatuSehat\VisitSync;
use App\Support\BranchContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Status kirim SATUSEHAT di dashboard (Planning §5.8: widget admin/perawat). */
class SatuSehatStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 7;

    protected ?string $heading = 'SATUSEHAT';

    /** Dua kartu per baris di HP, tiga berjajar bila wadahnya cukup lebar. */
    protected int|array|null $columns = ['default' => 2, '@xl' => 3, '!@lg' => 3];

    /**
     * Selama pengiriman otomatis belum aktif dan belum ada data kirim, tiga kartu "0" hanya memenuhi dashboard;
     * statusnya tetap terlihat di menu SATUSEHAT.
     */
    public static function canView(): bool
    {
        if (! (auth()->user()?->can('View:SatuSehat') ?? false)) {
            return false;
        }

        return VisitSync::enabled()
            || SatuSehatSync::query()->whereIn('branch_id', BranchContext::available()->pluck('id'))->exists();
    }

    protected function getDescription(): ?string
    {
        return VisitSync::enabled() ? 'Dikirim otomatis setelah rekam medis ditandatangani' : 'Pengiriman otomatis belum aktif';
    }

    protected function getStats(): array
    {
        $branches = BranchContext::available()->pluck('id');
        $base = fn () => SatuSehatSync::query()->whereIn('branch_id', $branches);
        $url = SatuSehatMonitor::getUrl();

        $problems = $base()->whereIn('status', [SatuSehatSyncStatus::Failed, SatuSehatSyncStatus::Blocked])->count();

        return [
            Stat::make('Diterima hari ini', $base()->where('status', SatuSehatSyncStatus::Sent)->whereDate('sent_at', today())->count())
                ->color('success')->url($url),
            Stat::make('Dalam antrean', $base()->where('status', SatuSehatSyncStatus::Pending)->count())->url($url),
            Stat::make('Perlu diperbaiki', $problems)
                ->description($problems > 0 ? 'Buka halaman SATUSEHAT untuk penyebab & cara memperbaiki' : 'Tidak ada')
                ->color($problems > 0 ? 'danger' : 'gray')
                ->url($url),
        ];
    }
}
