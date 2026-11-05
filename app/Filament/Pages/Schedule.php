<?php

namespace App\Filament\Pages;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Filament\Clinic\PatientSelect;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Resources\Appointments\AppointmentActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Visits\VisitResource;
use App\Filament\Support\NavGroup;
use App\Models\Appointment;
use App\Models\Chair;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\Clinic\VisitFlow;
use App\Support\BranchContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Papan jadwal harian per kursi + antrean (mockup "Jadwal & antrean").
 */
class Schedule extends Page
{
    protected string $view = 'filament.pages.schedule';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::DAILY;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Jadwal & antrean';

    protected static ?string $title = 'Jadwal & antrean';

    protected static ?string $slug = 'jadwal';

    /** Piksel per menit di papan jadwal. */
    public const SCALE = 1.2;

    #[Url]
    public string $date = '';

    public ?int $branchId = null;

    public ?int $selectedAppointmentId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ViewAny:Appointment') ?? false;
    }

    public function mount(): void
    {
        $this->date = $this->date !== '' ? Carbon::parse($this->date)->toDateString() : today()->toDateString();
        $this->branchId = BranchContext::id();
    }

    public function updatedBranchId(?int $value): void
    {
        if ($value) {
            BranchContext::set($value);
        }

        $this->selectedAppointmentId = null;
    }

    public function shiftDate(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
        $this->selectedAppointmentId = null;
    }

    public function goToday(): void
    {
        $this->date = today()->toDateString();
    }

    public function select(int $appointmentId): void
    {
        $this->selectedAppointmentId = $appointmentId;
    }

    public function getSubheading(): ?string
    {
        $day = Carbon::parse($this->date);

        return $day->translatedFormat('l, j F Y').($day->isToday() ? ' · hari ini' : '');
    }

    /** @return Collection<int, Appointment> */
    protected function appointments(): Collection
    {
        $query = Appointment::query()
            ->with(['patient', 'practitioner.user', 'chair', 'visit'])
            ->where('branch_id', $this->branchId)
            ->whereDate('start_at', $this->date)
            ->whereNotIn('status', [AppointmentStatus::Cancelled])
            ->orderBy('start_at');

        return AppointmentResource::scopeToOwnPatientsForDoctor($query)->get();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $appointments = $this->appointments();
        $chairs = Chair::query()->where('branch_id', $this->branchId)->where('is_active', true)->orderBy('name')->get();

        /** @var list<array{id: int|null, name: string, appointments: Collection<int, Appointment>}> $columns */
        $columns = $chairs->map(fn (Chair $chair): array => [
            'id' => $chair->id,
            'name' => $chair->name,
            'appointments' => $appointments->where('chair_id', $chair->id)->values(),
        ])->values()->all();

        $unassigned = $appointments->whereNull('chair_id')->values();

        if ($unassigned->isNotEmpty() || $columns === []) {
            $columns[] = ['id' => null, 'name' => 'Belum ada kursi', 'appointments' => $unassigned];
        }

        $startHour = min(8, (int) ($appointments->min(fn (Appointment $a) => (int) $a->start_at->format('G')) ?? 8));
        $endHour = max(18, (int) ($appointments->max(fn (Appointment $a) => (int) $a->end_at->format('G')) ?? 18) + 1);

        $visits = Visit::query()
            ->with(['patient', 'practitioner.user', 'chair', 'medicalRecord'])
            ->where('branch_id', $this->branchId)
            ->whereDate('visit_date', $this->date)
            ->whereIn('status', [VisitStatus::Waiting, VisitStatus::InTreatment, VisitStatus::Done])
            ->orderBy('queue_number')
            ->get();

        return [
            'columns' => $columns,
            'startHour' => $startHour,
            'endHour' => $endHour,
            'scale' => self::SCALE,
            'branches' => BranchContext::options(),
            'waiting' => $visits->where('status', VisitStatus::Waiting)->values(),
            'inTreatment' => $visits->where('status', VisitStatus::InTreatment)->values(),
            'done' => $visits->where('status', VisitStatus::Done)->values(),
            'selected' => $this->selectedAppointmentId ? $appointments->firstWhere('id', $this->selectedAppointmentId) : null,
            'isToday' => Carbon::parse($this->date)->isToday(),
            'nowOffset' => (now()->hour * 60 + now()->minute - $startHour * 60) * self::SCALE,
            'canExamine' => auth()->user()?->can('View:MedicalRecord') ?? false,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->walkInAction(),
            $this->createAppointmentAction(),
        ];
    }

    public function createAppointmentAction(): Action
    {
        return Action::make('createAppointment')
            ->label('Buat janji')
            ->icon(Heroicon::OutlinedPlus)
            ->authorize(fn (): bool => auth()->user()?->can('Create:Appointment') ?? false)
            ->modalHeading('Buat janji temu')
            ->modalWidth('2xl')
            ->schema(AppointmentForm::components())
            ->fillForm(fn (array $arguments): array => [
                'branch_id' => $this->branchId,
                'date' => $this->date,
                'duration' => 30,
                'chair_id' => $arguments['chair'] ?? null,
                'start_time' => $arguments['time'] ?? null,
            ])
            ->action(function (array $data): void {
                $attributes = $this->validateAppointment($data, null);
                $appointment = Appointment::create([...$attributes, 'status' => AppointmentStatus::Booked]);
                $this->selectedAppointmentId = $appointment->id;
                Notification::make()->title('Janji temu dibuat')->success()->send();
            });
    }

    public function editAppointmentAction(): Action
    {
        return Action::make('editAppointment')
            ->label('Ubah')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->record(fn (array $arguments): ?Appointment => Appointment::find($arguments['appointment'] ?? null))
            ->visible(fn (?Appointment $record): bool => $record !== null
                && in_array($record->status, [AppointmentStatus::Booked, AppointmentStatus::Confirmed], true))
            ->authorize(fn (?Appointment $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->modalHeading('Ubah janji temu')
            ->modalWidth('2xl')
            ->schema(AppointmentForm::components())
            ->fillForm(fn (Appointment $record): array => AppointmentForm::fromRecord($record))
            ->action(function (Appointment $record, array $data): void {
                $record->update($this->validateAppointment($data, $record->id));
                Notification::make()->title('Janji temu diperbarui')->success()->send();
            });
    }

    public function confirmAppointmentAction(): Action
    {
        return $this->bindAppointment(AppointmentActions::confirm());
    }

    public function checkInAppointmentAction(): Action
    {
        return $this->bindAppointment(AppointmentActions::checkIn());
    }

    public function noShowAppointmentAction(): Action
    {
        return $this->bindAppointment(AppointmentActions::noShow());
    }

    public function cancelAppointmentAction(): Action
    {
        return $this->bindAppointment(AppointmentActions::cancel());
    }

    public function walkInAction(): Action
    {
        return Action::make('walkIn')
            ->label('Pasien datang langsung')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->authorize(fn (): bool => auth()->user()?->can('Create:Visit') ?? false)
            ->visible(fn (): bool => Carbon::parse($this->date)->isToday())
            ->modalHeading('Pasien datang langsung (tanpa janji)')
            ->schema([
                PatientSelect::make(),
                Select::make('practitioner_id')->label('Dokter')->options(fn (): array => PractitionerOptions::dentists())->searchable()->required(),
                Select::make('chair_id')
                    ->label('Kursi')
                    ->options(fn (): array => Chair::where('branch_id', $this->branchId)->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
                TextInput::make('chief_complaint')->label('Keluhan')->maxLength(255),
            ])
            ->action(function (array $data): void {
                $visit = VisitFlow::walkIn([
                    'branch_id' => (int) $this->branchId,
                    'patient_id' => (int) $data['patient_id'],
                    'practitioner_id' => (int) $data['practitioner_id'],
                    'chair_id' => filled($data['chair_id'] ?? null) ? (int) $data['chair_id'] : null,
                    'payer_id' => Patient::find($data['patient_id'])?->default_payer_id,
                    'chief_complaint' => $data['chief_complaint'] ?? null,
                ]);

                Notification::make()->title('Masuk antrean nomor '.$visit->queue_number)->success()->send();
            });
    }

    public function startVisitAction(): Action
    {
        return Action::make('startVisit')
            ->label('Panggil')
            ->button()
            ->size('sm')
            ->record(fn (array $arguments): ?Visit => Visit::find($arguments['visit'] ?? null))
            ->authorize(fn (?Visit $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(function (Visit $record): void {
                try {
                    VisitFlow::start($record);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($record->patient->name.' dipanggil ke kursi')->success()->send();
            });
    }

    public function examUrl(Visit $visit): string
    {
        return VisitResource::getUrl('examine', ['record' => $visit]);
    }

    private function bindAppointment(Action $action): Action
    {
        return $action->record(fn (array $arguments): ?Appointment => Appointment::find($arguments['appointment'] ?? null));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateAppointment(array $data, ?int $ignoreId): array
    {
        try {
            return AppointmentForm::toAttributes($data, $ignoreId);
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak bisa disimpan')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();

            // Modal tetap terbuka agar isian bisa diperbaiki.
            throw new Halt;
        }
    }
}
