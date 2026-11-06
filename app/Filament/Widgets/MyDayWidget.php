<?php

namespace App\Filament\Widgets;

use App\Services\Reports\DayPlanner;
use App\Support\BranchContext;
use App\Support\CommissionScope;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

/** Dashboard dokter: pasien saya hari ini dengan penanda (baru, alergi/penyakit sistemik, rencana tertunda, recall, saldo). */
class MyDayWidget extends Widget
{
    use HasWidgetShield {
        canView as shieldCanView;
    }

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.appointment-list';

    public static function canView(): bool
    {
        return static::shieldCanView() && CommissionScope::ownPractitionerId(auth()->user()) !== null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $pid = CommissionScope::ownPractitionerId(auth()->user());

        return [
            'heading' => 'Pasien saya hari ini',
            'description' => today()->locale('id')->translatedFormat('l, j F Y'),
            'items' => DayPlanner::appointments(today(), BranchContext::available()->pluck('id')->all(), $pid, auth()->user()),
            'showDoctor' => false,
            'empty' => 'Tidak ada janji hari ini.',
        ];
    }
}
