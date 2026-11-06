<?php

namespace App\Filament\Widgets;

use App\Services\Reports\DayPlanner;
use App\Support\BranchContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

/** Dashboard resepsionis: pasien yang perlu dihubungi (recall, rencana belum dijadwalkan, cicilan jatuh tempo). */
class ToContactWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.simple-table';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $rows = DayPlanner::toContact(BranchContext::available()->pluck('id')->all())->take(12);

        return [
            'heading' => 'Perlu dihubungi',
            'description' => 'Recall jatuh tempo ≤ 7 hari & rencana disetujui yang pasiennya belum punya janji, serta cicilan jatuh tempo ≤ 7 hari.',
            'columns' => [['label' => 'Pasien'], ['label' => 'Alasan'], ['label' => 'Tanggal']],
            'rows' => $rows->map(fn (array $row): array => [
                ['text' => $row['patient']->name, 'sub' => $row['patient']->phone ?? 'No. HP belum ada'],
                $row['reason'],
                ['text' => $row['due']?->format('d/m/Y') ?? '—', 'color' => $row['due']?->lt(today()) ? 'danger' : null],
            ])->all(),
            'empty' => 'Tidak ada yang perlu dihubungi.',
        ];
    }
}
