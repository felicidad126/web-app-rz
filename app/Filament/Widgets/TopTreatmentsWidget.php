<?php

namespace App\Filament\Widgets;

use App\Enums\Kpi;
use App\Services\Reports\Metrics;
use App\Services\Reports\TreatmentMargins;
use App\Support\BranchContext;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

/** Top tindakan berdasarkan omzet & margin, bulan berjalan (owner; memuat laba). */
class TopTreatmentsWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.simple-table';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $rows = TreatmentMargins::between(now()->startOfMonth(), today(), BranchContext::available()->pluck('id')->all())->take(10);

        return [
            'heading' => 'Top tindakan bulan ini',
            'description' => 'Berdasarkan omzet (termasuk hari ini). '.Kpi::Margin->definition(),
            'columns' => [
                ['label' => 'Tindakan'], ['label' => 'Jumlah', 'align' => 'end'], ['label' => 'Omzet', 'align' => 'end', 'tip' => Kpi::Production->definition()],
                ['label' => 'Bahan + lab', 'align' => 'end'], ['label' => 'Jasa dokter', 'align' => 'end'],
                ['label' => 'Margin', 'align' => 'end', 'tip' => Kpi::Margin->definition()], ['label' => 'Margin %', 'align' => 'end'],
            ],
            'rows' => $rows->map(fn ($r): array => [
                $r->name,
                rtrim(rtrim(number_format($r->qty, 2, ',', '.'), '0'), ','),
                Money::format($r->revenue, false),
                Money::format($r->material + $r->lab, false),
                Money::format($r->commission, false),
                ['text' => Money::format($r->margin, false), 'color' => $r->margin < 0 ? 'danger' : null],
                Metrics::percent(Metrics::ratio($r->margin, $r->revenue)),
            ])->all(),
            'empty' => 'Belum ada tindakan selesai bulan ini.',
        ];
    }
}
