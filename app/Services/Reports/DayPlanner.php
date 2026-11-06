<?php

namespace App\Services\Reports;

use App\Enums\AppointmentStatus;
use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LabOrderStatus;
use App\Enums\RecallStatus;
use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\PractitionerSchedule;
use App\Models\Recall;
use App\Models\ScheduleException;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data hari kerja untuk briefing pagi, dashboard dokter, dan resepsionis: janji dengan penanda pasien,
 * slot kosong, dan daftar pasien yang perlu dihubungi.
 */
final class DayPlanner
{
    /**
     * Janji pada tanggal itu dengan penanda per pasien.
     *
     * @param  list<int>  $branchIds
     * @return Collection<int, array{appointment: Appointment, flags: list<array{label: string, color: string}>}>
     */
    public static function appointments(Carbon $date, array $branchIds, ?int $practitionerId = null, ?User $viewer = null): Collection
    {
        $appointments = Appointment::query()->with(['patient', 'practitioner.user', 'chair'])
            ->whereIn('branch_id', $branchIds)->whereDate('start_at', $date)
            ->whereNotIn('status', [AppointmentStatus::Cancelled])
            ->when($practitionerId !== null, fn ($q) => $q->where('practitioner_id', $practitionerId))
            ->orderBy('start_at')->get();

        $flags = self::patientFlags($appointments->pluck('patient_id')->unique()->values()->all(), $date, $viewer);

        return $appointments->map(fn (Appointment $a): array => ['appointment' => $a, 'flags' => $flags[$a->patient_id] ?? []]);
    }

    /**
     * Penanda per pasien: pasien baru, saldo belum lunas, recall terlambat, rencana tertunda, ulang tahun, lab belum kembali,
     * serta alergi/penyakit sistemik (hanya untuk pengguna yang boleh melihat rekam medis; isinya tidak ditampilkan).
     *
     * @param  list<int>  $patientIds
     * @return array<int, list<array{label: string, color: string}>>
     */
    public static function patientFlags(array $patientIds, Carbon $date, ?User $viewer = null): array
    {
        if ($patientIds === []) {
            return [];
        }

        $seen = Visit::query()->whereIn('patient_id', $patientIds)->whereIn('status', [VisitStatus::Done, VisitStatus::Billed])
            ->whereDate('visit_date', '<', $date)->distinct()->pluck('patient_id')->flip();
        $balances = Invoice::query()->whereIn('patient_id', $patientIds)->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Partial])
            ->get(['patient_id', 'patient_portion', 'paid_total'])->groupBy('patient_id')
            ->map(fn (Collection $rows): int => (int) $rows->sum(fn (Invoice $i): int => max(0, $i->patient_portion - $i->paid_total)));
        $recalls = Recall::query()->whereIn('patient_id', $patientIds)->whereIn('status', [RecallStatus::Pending, RecallStatus::Contacted])
            ->whereDate('due_date', '<', $date)->distinct()->pluck('patient_id')->flip();
        $plans = TreatmentPlan::query()->whereIn('patient_id', $patientIds)->whereIn('status', [TreatmentPlanStatus::Accepted, TreatmentPlanStatus::PartiallyDone])
            ->whereHas('items', fn ($q) => $q->where('status', TreatmentPlanItemStatus::Planned))->distinct()->pluck('patient_id')->flip();
        $labs = LabOrder::query()->whereIn('patient_id', $patientIds)->whereIn('status', [LabOrderStatus::Created, LabOrderStatus::Sent])
            ->get(['patient_id', 'due_at'])->groupBy('patient_id');
        $clinical = $viewer?->can('View:MedicalRecord') ?? false;
        $patients = Patient::query()->whereIn('id', $patientIds)->get(['id', 'birth_date', 'allergies', 'systemic_diseases'])->keyBy('id');

        $result = [];

        foreach ($patientIds as $id) {
            $list = [];
            $patient = $patients->get($id);

            if (! isset($seen[$id])) {
                $list[] = ['label' => 'Pasien baru', 'color' => 'info'];
            }

            if (($balances[$id] ?? 0) > 0) {
                $list[] = ['label' => 'Saldo terutang Rp '.number_format($balances[$id], 0, ',', '.'), 'color' => 'warning'];
            }

            if (isset($recalls[$id])) {
                $list[] = ['label' => 'Recall terlambat', 'color' => 'warning'];
            }

            if (isset($plans[$id])) {
                $list[] = ['label' => 'Rencana tertunda', 'color' => 'gray'];
            }

            if ($labs->has($id)) {
                $late = $labs[$id]->contains(fn (LabOrder $o): bool => $o->due_at !== null && $o->due_at->lt($date));
                $list[] = ['label' => $late ? 'Lab terlambat' : 'Lab belum kembali', 'color' => $late ? 'danger' : 'gray'];
            }

            if ($patient !== null && $patient->birth_date->format('m-d') === $date->format('m-d')) {
                $list[] = ['label' => 'Ulang tahun', 'color' => 'success'];
            }

            if ($clinical && $patient !== null && (filled($patient->getAttribute('allergies')) || filled($patient->getAttribute('systemic_diseases')))) {
                $list[] = ['label' => 'Alergi / penyakit sistemik', 'color' => 'danger'];
            }

            $result[$id] = $list;
        }

        return $result;
    }

    /**
     * Slot kosong ≥ $minMinutes per dokter: jam praktik dikurangi janji (tidak termasuk yang batal) dan pengecualian.
     *
     * @return list<array{practitioner: string, chair: string|null, start: string, end: string, minutes: int}>
     */
    public static function freeSlots(Carbon $date, int $branchId, int $minMinutes = 30): array
    {
        $schedules = PractitionerSchedule::query()->with(['practitioner.user', 'chair'])->where('branch_id', $branchId)
            ->where('is_active', true)->where('day_of_week', $date->dayOfWeekIso)->get();

        $slots = [];

        foreach ($schedules as $schedule) {
            $busy = Appointment::query()->where('practitioner_id', $schedule->practitioner_id)->whereDate('start_at', $date)
                ->whereNotIn('status', [AppointmentStatus::Cancelled])->get(['start_at', 'end_at'])
                ->map(fn (Appointment $a): array => [self::minutes($a->start_at->format('H:i')), self::minutes($a->end_at->format('H:i'))]);

            foreach (ScheduleException::query()->where('practitioner_id', $schedule->practitioner_id)->whereDate('date', $date)->get() as $exception) {
                $busy->push($exception->start_time === null
                    ? [0, 24 * 60]
                    : [self::minutes($exception->start_time), self::minutes((string) $exception->end_time)]);
            }

            $cursor = self::minutes($schedule->start_time);
            $end = self::minutes($schedule->end_time);

            foreach ($busy->sortBy(0)->values() as [$from, $to]) {
                if ($from - $cursor >= $minMinutes && $from <= $end) {
                    $slots[] = self::slot($schedule, $cursor, min($from, $end));
                }

                $cursor = max($cursor, $to);
            }

            if ($end - $cursor >= $minMinutes) {
                $slots[] = self::slot($schedule, $cursor, $end);
            }
        }

        usort($slots, fn (array $a, array $b): int => [$a['start'], $a['practitioner']] <=> [$b['start'], $b['practitioner']]);

        return $slots;
    }

    /**
     * Pasien yang perlu dihubungi: recall jatuh tempo, rencana disetujui belum dijadwalkan, cicilan jatuh tempo.
     *
     * @param  list<int>  $branchIds
     * @return Collection<int, array{patient: Patient, reason: string, due: Carbon|null}>
     */
    public static function toContact(array $branchIds, int $days = 7): Collection
    {
        $booked = Appointment::query()->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::Confirmed])
            ->where('start_at', '>=', now())->distinct()->pluck('patient_id')->flip();
        $list = collect();

        Recall::query()->with('patient')->whereIn('status', [RecallStatus::Pending, RecallStatus::Contacted])
            ->where(fn ($q) => $q->whereIn('branch_id', $branchIds)->orWhereNull('branch_id'))
            ->whereDate('due_date', '<=', today()->addDays($days))->orderBy('due_date')->limit(30)->get()
            ->reject(fn (Recall $r): bool => isset($booked[$r->patient_id]))
            ->each(fn (Recall $r) => $list->push(['patient' => $r->patient, 'reason' => 'Recall: '.$r->reason, 'due' => $r->due_date]));

        Kpis::unscheduledPlans($branchIds)->take(15)
            ->each(fn (array $row) => $list->push([
                'patient' => $row['plan']->patient,
                'reason' => 'Rencana disetujui: '.$row['plan']->title.' · Rp '.number_format($row['value'], 0, ',', '.'),
                'due' => $row['plan']->decided_at,
            ]));

        Installment::query()->with('patientPackage.patient')
            ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Billed])
            ->whereDate('due_date', '<=', today()->addDays($days))
            ->whereHas('patientPackage', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->orderBy('due_date')->limit(15)->get()
            ->each(fn (Installment $i) => $list->push([
                'patient' => $i->patientPackage->patient,
                'reason' => $i->label().' · Rp '.number_format($i->amount, 0, ',', '.'),
                'due' => $i->due_date,
            ]));

        return $list->sortBy(fn (array $row): string => $row['due']?->toDateString() ?? '9999')->values();
    }

    /** @return array{practitioner: string, chair: string|null, start: string, end: string, minutes: int} */
    private static function slot(PractitionerSchedule $schedule, int $from, int $to): array
    {
        return [
            'practitioner' => $schedule->practitioner->displayName(),
            'chair' => $schedule->chair?->name,
            'start' => sprintf('%02d:%02d', intdiv($from, 60), $from % 60),
            'end' => sprintf('%02d:%02d', intdiv($to, 60), $to % 60),
            'minutes' => $to - $from,
        ];
    }

    private static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return $h * 60 + $m;
    }
}
