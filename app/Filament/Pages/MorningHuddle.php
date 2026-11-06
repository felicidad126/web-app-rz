<?php

namespace App\Filament\Pages;

use App\Enums\AppointmentStatus;
use App\Enums\LabOrderStatus;
use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Enums\VisitStatus;
use App\Filament\Clusters\Stock\StockActions;
use App\Filament\Support\NavGroup;
use App\Models\Appointment;
use App\Models\LabOrder;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\Visit;
use App\Services\Reports\DailyMetrics;
use App\Services\Reports\DayPlanner;
use App\Services\Reports\Metrics;
use App\Services\Reports\Targets;
use App\Support\BranchContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Briefing pagi (Planning §5.8, mockup "Huddle"): kemarin (produksi, kas, pasien, tidak datang, pulang tanpa janji),
 * hari ini (jadwal per dokter dengan penanda pasien, slot kosong), besok (slot kosong & pasien untuk dihubungi).
 */
class MorningHuddle extends Page
{
    protected string $view = 'filament.pages.morning-huddle';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::DAILY;

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Briefing pagi';

    protected static ?string $title = 'Briefing pagi';

    protected static ?string $slug = 'briefing-pagi';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:MorningHuddle') ?? false;
    }

    public function mount(): void
    {
        activity('access')->causedBy(auth()->user())->event('viewed')
            ->withProperties(['branch_id' => BranchContext::id()])->log('Membuka briefing pagi');
    }

    public function getSubheading(): ?string
    {
        $branch = BranchContext::available()->firstWhere('id', BranchContext::id())->name ?? 'Belum ada cabang';

        return today()->locale('id')->translatedFormat('l, j F Y').' · '.$branch.' · angka kemarin dari snapshot 00:30';
    }

    protected function getHeaderActions(): array
    {
        return [
            StockActions::branchSwitcher(),
            Action::make('print')->label('Cetak')->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->extraAttributes(['onclick' => 'window.print(); return false;']),
            Action::make('schedule')->label('Buka jadwal hari ini')->icon(Heroicon::OutlinedCalendarDays)
                ->url(fn (): string => Schedule::getUrl())
                ->visible(fn (): bool => Schedule::canAccess()),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $branchId = BranchContext::id();

        if ($branchId === null) {
            return ['branchId' => null];
        }

        /** @var User $user */
        $user = auth()->user();
        $yesterday = today()->subDay();
        $ids = [$branchId];

        $today = DayPlanner::appointments(today(), $ids, null, $user);
        $tomorrow = Appointment::query()->where('branch_id', $branchId)->whereDate('start_at', today()->addDay())->whereNot('status', AppointmentStatus::Cancelled)->get(['status']);
        $average = DailyMetrics::averagePerVisit($branchId, $yesterday);
        $dailyTarget = Targets::daily(today(), $branchId);
        $scheduledValue = $today->count() * $average;

        return [
            'branchId' => $branchId,
            'yesterday' => [
                'date' => $yesterday,
                'production' => Metrics::sum('production', $yesterday, $yesterday, $ids),
                'target' => Targets::daily($yesterday, $branchId),
                'cash' => Metrics::sum('cash_in', $yesterday, $yesterday, $ids),
                'visits' => Metrics::sum('visits', $yesterday, $yesterday, $ids),
                'new' => Metrics::sum('new_patients', $yesterday, $yesterday, $ids),
                'no_shows' => Metrics::sum('no_shows', $yesterday, $yesterday, $ids),
                'no_show_value' => Metrics::sum('no_show_value', $yesterday, $yesterday, $ids),
                'left_without_next' => $this->leftWithoutNext($yesterday, $branchId),
            ],
            'today' => [
                'groups' => $today->groupBy(fn (array $row): string => trim(($row['appointment']->chair?->name ? $row['appointment']->chair->name.' · ' : '').$row['appointment']->practitioner->displayName())),
                'count' => $today->count(),
                'confirmed' => $today->filter(fn (array $row): bool => in_array($row['appointment']->status, [AppointmentStatus::Confirmed, AppointmentStatus::Arrived], true))->count(),
                'scheduled_value' => $scheduledValue,
                'target' => $dailyTarget,
                'slots' => DayPlanner::freeSlots(today(), $branchId),
            ],
            'tomorrow' => [
                'date' => today()->addDay(),
                'count' => $tomorrow->count(),
                'confirmed' => $tomorrow->where('status', AppointmentStatus::Confirmed)->count(),
                'slots' => DayPlanner::freeSlots(today()->addDay(), $branchId),
                'contacts' => DayPlanner::toContact($ids, 3)->take(8),
            ],
            'average' => $average,
        ];
    }

    /**
     * Pasien yang kemarin datang dan masih punya rencana disetujui / pekerjaan lab, tetapi belum punya janji berikutnya.
     *
     * @return list<array{name: string, note: string, late: bool}>
     */
    private function leftWithoutNext(Carbon $date, int $branchId): array
    {
        $patientIds = Visit::query()->where('branch_id', $branchId)->whereDate('visit_date', $date)
            ->whereIn('status', [VisitStatus::Done, VisitStatus::Billed])->distinct()->pluck('patient_id');
        $booked = DayPlanner::appointments(today(), [$branchId])->pluck('appointment.patient_id')
            ->merge(Appointment::query()->whereIn('patient_id', $patientIds)->where('start_at', '>=', now())
                ->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::Confirmed])->pluck('patient_id'))
            ->unique()->flip();
        $pending = $patientIds->reject(fn ($id): bool => isset($booked[$id]))->values();

        $plans = TreatmentPlan::query()->with('patient')->whereIn('patient_id', $pending)
            ->whereIn('status', [TreatmentPlanStatus::Accepted, TreatmentPlanStatus::PartiallyDone])
            ->withSum(['items as value' => fn ($q) => $q->where('status', TreatmentPlanItemStatus::Planned)], 'price_estimate')->get()
            ->filter(fn (TreatmentPlan $p): bool => (int) $p->getAttribute('value') > 0)
            ->map(fn (TreatmentPlan $p): array => ['name' => $p->patient->name, 'note' => $p->title.' disetujui · Rp '.number_format((int) $p->getAttribute('value'), 0, ',', '.'), 'late' => false]);

        $labs = LabOrder::query()->with('patient')->whereIn('patient_id', $pending)->whereIn('status', LabOrderStatus::active())->get()
            ->map(fn (LabOrder $o): array => ['name' => $o->patient->name, 'note' => $o->work_type->getLabel().' '.($o->status === LabOrderStatus::Received || $o->status === LabOrderStatus::Checked ? 'siap dipasang' : 'masih di lab'), 'late' => $o->isLate()]);

        return $plans->merge($labs)->values()->all();
    }
}
