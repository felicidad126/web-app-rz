<?php

namespace App\Filament\Pages;

use App\Enums\VisitStatus;
use App\Filament\Support\NavGroup;
use App\Models\Visit;
use App\Support\BranchContext;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Papan antrean untuk layar ruang tunggu. Nama pasien disamarkan (UU PDP).
 */
class QueueBoard extends Page
{
    protected string $view = 'filament.pages.queue-board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTv;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::DAILY;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Papan antrean';

    protected static ?string $title = 'Papan antrean';

    protected static ?string $slug = 'papan-antrean';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ViewAny:Visit') ?? false;
    }

    public static function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = array_shift($parts) ?? '';

        return trim($first.' '.implode(' ', array_map(fn (string $p): string => mb_substr($p, 0, 1).'.', $parts)));
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $visits = Visit::query()
            ->with(['patient', 'chair', 'practitioner.user'])
            ->where('branch_id', BranchContext::id())
            ->whereDate('visit_date', today())
            ->whereIn('status', [VisitStatus::Waiting, VisitStatus::InTreatment])
            ->orderBy('queue_number')
            ->get();

        return [
            'branch' => BranchContext::available()->firstWhere('id', BranchContext::id())?->name,
            'serving' => $visits->where('status', VisitStatus::InTreatment)->sortByDesc('started_at')->values(),
            'waiting' => $visits->where('status', VisitStatus::Waiting)->values(),
        ];
    }
}
