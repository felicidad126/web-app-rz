<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\Kpi;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\Visit;
use App\Services\Reports\DayPlanner;
use App\Services\Reports\Metrics;
use App\Support\BranchContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dashboard resepsionis: janji hari ini & besok, antrean & waktu tunggu, slot kosong besok. */
class FrontDeskWidget extends StatsOverviewWidget
{
    use HasWidgetShield {
        canView as shieldCanView;
    }

    protected static ?int $sort = 1;

    /** Dua kartu per baris di HP, empat berjajar bila wadahnya cukup lebar. */
    protected int|array|null $columns = ['default' => 2, '@3xl' => 4, '!@lg' => 4];

    public static function canView(): bool
    {
        return static::shieldCanView() && BranchContext::id() !== null;
    }

    protected function getHeading(): ?string
    {
        return 'Meja depan · '.BranchContext::available()->firstWhere('id', BranchContext::id())?->name;
    }

    protected function getStats(): array
    {
        $branchId = (int) BranchContext::id();
        $count = fn ($date) => Appointment::query()->where('branch_id', $branchId)->whereDate('start_at', $date)->whereNot('status', AppointmentStatus::Cancelled);
        $today = $count(today())->count();
        $todayConfirmed = $count(today())->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Arrived])->count();
        $tomorrow = $count(today()->addDay())->count();
        $tomorrowConfirmed = $count(today()->addDay())->where('status', AppointmentStatus::Confirmed)->count();
        $waiting = Visit::query()->where('branch_id', $branchId)->whereDate('visit_date', today())->where('status', VisitStatus::Waiting)->count();
        $waitMinutes = Metrics::sum('wait_minutes', today(), today(), [$branchId]);
        $waitCount = Metrics::sum('wait_count', today(), today(), [$branchId]);
        $slots = DayPlanner::freeSlots(today()->addDay(), $branchId);

        return [
            Stat::make('Janji hari ini', (string) $today)->description($todayConfirmed.' dikonfirmasi / datang'),
            Stat::make('Menunggu sekarang', (string) $waiting)
                ->description($waitCount > 0 ? 'Rata-rata tunggu hari ini '.(int) round($waitMinutes / $waitCount).' menit' : 'Belum ada pasien diperiksa')
                ->extraAttributes(['title' => Kpi::WaitTime->getLabel().': '.Kpi::WaitTime->definition()]),
            Stat::make('Janji besok', (string) $tomorrow)->description($tomorrowConfirmed.' dikonfirmasi'),
            Stat::make('Slot kosong besok', (string) count($slots))
                ->description(collect($slots)->take(3)->map(fn (array $s): string => $s['start'].'–'.$s['end'])->implode(' · ') ?: 'Jadwal penuh / tidak ada praktik'),
        ];
    }
}
