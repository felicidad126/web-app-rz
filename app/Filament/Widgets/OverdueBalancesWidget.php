<?php

namespace App\Filament\Widgets;

use App\Enums\Kpi;
use App\Services\Reports\Kpis;
use App\Support\BranchContext;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

/** Saldo terutang lebih dari 30 hari + ringkasan umur piutang. */
class OverdueBalancesWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.simple-table';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $ids = BranchContext::available()->pluck('id')->all();
        $aging = Kpis::receivablesAging($ids);
        $rows = Kpis::overdueInvoices($ids);

        return [
            'heading' => 'Saldo terutang lebih dari 30 hari',
            'description' => 'Piutang per umur: '.collect($aging)->map(fn (int $v, string $k): string => $k.' hari '.Money::format($v))->implode(' · ')
                .' · sisa cicilan paket '.Money::format(Kpis::outstandingInstallments($ids)),
            'columns' => [
                ['label' => 'Pasien'],
                ['label' => 'Saldo', 'align' => 'end', 'tip' => Kpi::Receivables->definition()],
                ['label' => 'Umur', 'align' => 'end'],
                ['label' => 'Janji berikutnya'],
            ],
            'rows' => $rows->map(fn (array $row): array => [
                ['text' => $row['invoice']->patient->name, 'sub' => $row['invoice']->branch->name.' · '.$row['invoice']->label()],
                Money::format($row['balance'], false),
                ['text' => $row['age'].' hari', 'color' => $row['age'] > 90 ? 'danger' : ($row['age'] > 60 ? 'warning' : null)],
                $row['next'] ? ($row['next']->start_at->isToday() ? 'Hari ini '.$row['next']->start_at->format('H:i').' · tagih di kasir' : $row['next']->start_at->format('d/m H:i')) : 'Belum ada · telepon',
            ])->all(),
            'footer' => $rows->isEmpty() ? [] : ['Total', Money::format((int) $rows->sum('balance'), false), '', ''],
            'note' => 'Umur dihitung dari tanggal tagihan terbit.',
            'empty' => 'Tidak ada saldo terutang lebih dari 30 hari.',
        ];
    }
}
