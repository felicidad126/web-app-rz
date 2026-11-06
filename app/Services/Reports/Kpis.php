<?php

namespace App\Services\Reports;

use App\Enums\AppointmentStatus;
use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\TreatmentPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** KPI titik waktu (piutang, rencana, cicilan) yang dihitung langsung, bukan dari snapshot harian. */
final class Kpis
{
    public const AGING_BUCKETS = ['0–30', '31–60', '61–90', '>90'];

    /**
     * Piutang pasien per umur tagihan.
     *
     * @param  list<int>  $branchIds
     * @return array<string, int>
     */
    public static function receivablesAging(array $branchIds): array
    {
        $buckets = array_fill_keys(self::AGING_BUCKETS, 0);

        Invoice::query()->whereIn('branch_id', $branchIds)->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Partial])
            ->get(['id', 'issued_at', 'patient_portion', 'paid_total'])
            ->each(function (Invoice $invoice) use (&$buckets): void {
                $balance = max(0, $invoice->patient_portion - $invoice->paid_total);
                $buckets[self::bucket(self::age($invoice))] += $balance;
            });

        return $buckets;
    }

    /**
     * Tagihan terutang lebih dari $minDays hari, terlama dulu, dengan janji berikutnya pasien.
     *
     * @param  list<int>  $branchIds
     * @return Collection<int, array{invoice: Invoice, balance: int, age: int, next: Appointment|null}>
     */
    public static function overdueInvoices(array $branchIds, int $minDays = 31, int $limit = 10): Collection
    {
        $invoices = Invoice::query()->with(['patient', 'branch'])->whereIn('branch_id', $branchIds)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Partial])
            ->whereDate('issued_at', '<=', today()->subDays($minDays))
            ->orderBy('issued_at')->limit($limit)->get();

        $next = self::nextAppointments($invoices->pluck('patient_id')->unique()->all());

        return $invoices->map(fn (Invoice $invoice): array => [
            'invoice' => $invoice,
            'balance' => max(0, $invoice->patient_portion - $invoice->paid_total),
            'age' => self::age($invoice),
            'next' => $next->get($invoice->patient_id),
        ]);
    }

    /**
     * Penerimaan rencana: nilai disetujui ÷ nilai diajukan (rencana yang diajukan di periode).
     *
     * @param  list<int>  $branchIds
     * @return array{presented: int, accepted: int, ratio: float|null}
     */
    public static function caseAcceptance(Carbon $from, Carbon $to, array $branchIds, ?int $practitionerId = null): array
    {
        $plans = TreatmentPlan::query()
            ->whereNotNull('presented_at')->whereBetween('presented_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where(fn ($q) => $q->whereIn('branch_id', $branchIds)->orWhereNull('branch_id'))
            ->when($practitionerId !== null, fn ($q) => $q->where('practitioner_id', $practitionerId))
            ->withSum(['items as value' => fn ($q) => $q->where('status', '!=', TreatmentPlanItemStatus::Cancelled)], 'price_estimate')
            ->get(['id', 'status']);

        $accepted = [TreatmentPlanStatus::Accepted, TreatmentPlanStatus::PartiallyDone, TreatmentPlanStatus::Done];
        $presented = (int) $plans->sum('value');
        $acceptedValue = (int) $plans->filter(fn (TreatmentPlan $p): bool => in_array($p->status, $accepted, true))->sum('value');

        return ['presented' => $presented, 'accepted' => $acceptedValue, 'ratio' => Metrics::ratio($acceptedValue, $presented)];
    }

    /**
     * Rencana disetujui yang belum dikerjakan dan pasiennya belum punya janji berikutnya.
     *
     * @param  list<int>  $branchIds
     * @return Collection<int, array{plan: TreatmentPlan, value: int}>
     */
    public static function unscheduledPlans(array $branchIds, ?int $practitionerId = null): Collection
    {
        $booked = Appointment::query()->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::Confirmed])
            ->where('start_at', '>=', now())->distinct()->pluck('patient_id');

        return TreatmentPlan::query()->with(['patient', 'branch'])
            ->whereIn('status', [TreatmentPlanStatus::Accepted, TreatmentPlanStatus::PartiallyDone])
            ->where(fn ($q) => $q->whereIn('branch_id', $branchIds)->orWhereNull('branch_id'))
            ->when($practitionerId !== null, fn ($q) => $q->where('practitioner_id', $practitionerId))
            ->whereNotIn('patient_id', $booked)
            ->withSum(['items as value' => fn ($q) => $q->where('status', TreatmentPlanItemStatus::Planned)], 'price_estimate')
            ->orderBy('decided_at')
            ->get()
            ->filter(fn (TreatmentPlan $plan): bool => (int) $plan->getAttribute('value') > 0)
            ->map(fn (TreatmentPlan $plan): array => ['plan' => $plan, 'value' => (int) $plan->getAttribute('value')])
            ->values();
    }

    /**
     * Sisa cicilan paket yang belum lunas.
     *
     * @param  list<int>  $branchIds
     */
    public static function outstandingInstallments(array $branchIds): int
    {
        return (int) DB::table('installments')->join('patient_packages', 'patient_packages.id', '=', 'installments.patient_package_id')
            ->whereIn('patient_packages.branch_id', $branchIds)
            ->whereIn('installments.status', [InstallmentStatus::Pending->value, InstallmentStatus::Billed->value])
            ->sum('installments.amount');
    }

    /**
     * Janji berikutnya (belum lewat, belum batal) per pasien.
     *
     * @param  list<int>  $patientIds
     * @return Collection<int, Appointment>
     */
    public static function nextAppointments(array $patientIds): Collection
    {
        return Appointment::query()->whereIn('patient_id', $patientIds)
            ->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::Confirmed])
            ->where('start_at', '>=', today())->orderBy('start_at')->get()
            ->unique('patient_id')->keyBy('patient_id');
    }

    public static function bucket(int $age): string
    {
        return match (true) {
            $age <= 30 => '0–30',
            $age <= 60 => '31–60',
            $age <= 90 => '61–90',
            default => '>90',
        };
    }

    private static function age(Invoice $invoice): int
    {
        return max(0, (int) ($invoice->issued_at ?? now())->copy()->startOfDay()->diffInDays(today()));
    }
}
