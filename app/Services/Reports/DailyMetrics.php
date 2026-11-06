<?php

namespace App\Services\Reports;

use App\Enums\AppointmentStatus;
use App\Enums\CommissionStatus;
use App\Enums\DepositTransactionType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\VisitStatus;
use App\Models\Branch;
use App\Models\DailyMetric;
use App\Models\PractitionerSchedule;
use App\Models\ScheduleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Angka KPI per hari per cabang (dan per dokter bila relevan). Disimpan ke `daily_metrics` oleh job 00:30 sehingga
 * angka hari yang sudah lewat tidak berubah; hari ini selalu dihitung langsung. Definisi: App\Enums\Kpi.
 */
final class DailyMetrics
{
    /** Penanda bahwa snapshot (tanggal, cabang) sudah dibuat. */
    public const MARKER = '_snapshot';

    public const METRICS = [
        'production', 'material_cost', 'lab_cost', 'commission', 'visits', 'new_patients', 'cash_in',
        'appointments', 'no_shows', 'no_show_value', 'chair_minutes_available', 'chair_minutes_used', 'wait_minutes', 'wait_count',
    ];

    /** @var array<string, list<array{practitioner_id: int|null, metric: string, value: int}>> */
    private static array $live = [];

    /**
     * Simpan snapshot untuk satu tanggal. Tanpa $force, (tanggal, cabang) yang sudah ada dilewati.
     *
     * @param  list<int>|null  $branchIds
     */
    public static function snapshot(Carbon $date, ?array $branchIds = null, bool $force = false): int
    {
        $branchIds ??= Branch::query()->pluck('id')->all();
        $written = 0;

        foreach ($branchIds as $branchId) {
            $exists = DailyMetric::query()->whereDate('date', $date)->where('branch_id', $branchId)->where('metric', self::MARKER)->exists();

            if ($exists && ! $force) {
                continue;
            }

            $rows = self::compute($date, $branchId);

            DB::transaction(function () use ($date, $branchId, $rows): void {
                DailyMetric::query()->whereDate('date', $date)->where('branch_id', $branchId)->delete();
                $now = now();

                $insert = collect($rows)
                    ->reject(fn (array $row): bool => $row['value'] === 0)
                    ->map(fn (array $row): array => [...$row, 'date' => $date->toDateString(), 'branch_id' => $branchId, 'created_at' => $now, 'updated_at' => $now])
                    ->push(['date' => $date->toDateString(), 'branch_id' => $branchId, 'practitioner_id' => null, 'metric' => self::MARKER, 'value' => 1, 'created_at' => $now, 'updated_at' => $now]);

                foreach ($insert->chunk(200) as $chunk) {
                    DailyMetric::query()->insert($chunk->values()->all());
                }
            });

            $written++;
        }

        return $written;
    }

    /**
     * Angka satu hari di satu cabang (dihitung langsung dari transaksi).
     *
     * @return list<array{practitioner_id: int|null, metric: string, value: int}>
     */
    public static function compute(Carbon $date, int $branchId): array
    {
        $key = $date->toDateString().':'.$branchId;

        if ($date->isToday() && isset(self::$live[$key])) {
            return self::$live[$key];
        }

        $day = $date->toDateString();
        $rows = [];
        $add = function (?int $practitionerId, string $metric, int|float|string|null $value) use (&$rows): void {
            $rows[] = ['practitioner_id' => $practitionerId, 'metric' => $metric, 'value' => (int) round((float) $value)];
        };
        $doneVisits = fn () => DB::table('visits')->where('visits.branch_id', $branchId)->whereDate('visits.visit_date', $day)
            ->whereIn('visits.status', [VisitStatus::Done->value, VisitStatus::Billed->value]);

        // Produksi, bahan, lab per dokter pada baris tagihan.
        $production = $doneVisits()
            ->join('invoices', 'invoices.visit_id', '=', 'visits.id')
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', InvoiceStatus::Void->value)
            ->groupBy('invoice_items.practitioner_id')
            ->selectRaw('invoice_items.practitioner_id as pid, sum(invoice_items.net_amount + invoice_items.package_value) as production, sum(invoice_items.material_cost) as material, sum(invoice_items.lab_cost) as lab')
            ->get();

        foreach ($production as $row) {
            $add($row->pid, 'production', $row->production);
            $add($row->pid, 'material_cost', $row->material);
            $add($row->pid, 'lab_cost', $row->lab);
        }

        // Jasa dokter menurut tanggal tindakan.
        DB::table('commissions')->where('branch_id', $branchId)->whereDate('service_date', $day)
            ->where('status', '!=', CommissionStatus::Cancelled->value)
            ->groupBy('practitioner_id')->selectRaw('practitioner_id as pid, sum(amount) as total')->get()
            ->each(fn ($row) => $add((int) $row->pid, 'commission', $row->total));

        // Kunjungan selesai & pasien baru (kunjungan selesai pertama).
        $doneVisits()->groupBy('visits.practitioner_id')->selectRaw('visits.practitioner_id as pid, count(*) as total')->get()
            ->each(fn ($row) => $add((int) $row->pid, 'visits', $row->total));

        $doneVisits()
            ->whereNotExists(fn ($q) => $q->from('visits as earlier')->whereColumn('earlier.patient_id', 'visits.patient_id')
                ->whereIn('earlier.status', [VisitStatus::Done->value, VisitStatus::Billed->value])
                ->where(fn ($w) => $w->whereDate('earlier.visit_date', '<', $day)
                    ->orWhere(fn ($same) => $same->whereDate('earlier.visit_date', $day)->whereColumn('earlier.id', '<', 'visits.id'))))
            ->groupBy('visits.practitioner_id')->selectRaw('visits.practitioner_id as pid, count(distinct visits.patient_id) as total')->get()
            ->each(fn ($row) => $add((int) $row->pid, 'new_patients', $row->total));

        // Kas masuk (tingkat cabang).
        $payments = (int) DB::table('payments')->join('payment_transactions', 'payment_transactions.id', '=', 'payments.payment_transaction_id')
            ->where('payment_transactions.branch_id', $branchId)->whereDate('payment_transactions.paid_at', $day)
            ->where('payments.status', '!=', PaymentStatus::Void->value)->where('payments.method', '!=', PaymentMethod::Deposit->value)
            ->sum('payments.amount');
        $topups = (int) DB::table('deposit_transactions')->where('branch_id', $branchId)->whereDate('created_at', $day)
            ->where('type', DepositTransactionType::TopUp->value)->sum('amount');
        $refunds = (int) DB::table('refunds')->where('branch_id', $branchId)->where('status', RefundStatus::Paid->value)
            ->whereDate('paid_at', $day)->where('method', '!=', PaymentMethod::Deposit->value)->sum('amount');
        $add(null, 'cash_in', $payments + $topups - $refunds);

        // Janji & tidak datang.
        $appointments = DB::table('appointments')->where('branch_id', $branchId)->whereDate('start_at', $day)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->groupBy('practitioner_id')
            ->selectRaw('practitioner_id as pid, count(*) as total, sum(case when status = ? then 1 else 0 end) as no_shows', [AppointmentStatus::NoShow->value])
            ->get();
        $average = self::averagePerVisit($branchId, $date);

        foreach ($appointments as $row) {
            $add((int) $row->pid, 'appointments', $row->total);
            $add((int) $row->pid, 'no_shows', $row->no_shows);
            $add((int) $row->pid, 'no_show_value', (int) $row->no_shows * $average);
        }

        // Utilisasi kursi.
        foreach (self::scheduledMinutes($date, $branchId) as $pid => $minutes) {
            $add($pid, 'chair_minutes_available', $minutes);
        }

        $used = [];
        DB::table('appointments')->where('branch_id', $branchId)->whereDate('start_at', $day)->where('status', AppointmentStatus::Arrived->value)
            ->get(['practitioner_id', 'start_at', 'end_at'])
            ->each(function ($a) use (&$used): void {
                $used[$a->practitioner_id] = ($used[$a->practitioner_id] ?? 0) + max(0, (int) Carbon::parse($a->start_at)->diffInMinutes(Carbon::parse($a->end_at)));
            });
        $doneVisits()->whereNull('visits.appointment_id')->get(['visits.practitioner_id', 'visits.started_at', 'visits.finished_at'])
            ->each(function ($v) use (&$used): void {
                $minutes = $v->started_at && $v->finished_at ? (int) Carbon::parse($v->started_at)->diffInMinutes(Carbon::parse($v->finished_at)) : 30;
                $used[$v->practitioner_id] = ($used[$v->practitioner_id] ?? 0) + max(0, $minutes);
            });

        foreach ($used as $pid => $minutes) {
            $add((int) $pid, 'chair_minutes_used', $minutes);
        }

        // Waktu tunggu.
        $waits = [];
        DB::table('visits')->where('branch_id', $branchId)->whereDate('visit_date', $day)->whereNotNull('arrived_at')->whereNotNull('started_at')
            ->get(['practitioner_id', 'arrived_at', 'started_at'])
            ->each(function ($v) use (&$waits): void {
                $waits[$v->practitioner_id][] = max(0, (int) Carbon::parse($v->arrived_at)->diffInMinutes(Carbon::parse($v->started_at)));
            });

        foreach ($waits as $pid => $list) {
            $add((int) $pid, 'wait_minutes', array_sum($list));
            $add((int) $pid, 'wait_count', count($list));
        }

        if ($date->isToday()) {
            self::$live[$key] = $rows;
        }

        return $rows;
    }

    /** Lupakan hasil hitung hari ini yang di-cache dalam request (dipakai test). */
    public static function flush(): void
    {
        self::$live = [];
    }

    /**
     * Menit praktik per dokter di cabang pada tanggal itu, dari jadwal mingguan dikurangi pengecualian (cuti/izin).
     *
     * @return array<int, int>
     */
    public static function scheduledMinutes(Carbon $date, int $branchId, ?int $practitionerId = null): array
    {
        $schedules = PractitionerSchedule::query()->where('branch_id', $branchId)->where('is_active', true)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->when($practitionerId !== null, fn ($q) => $q->where('practitioner_id', $practitionerId))
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $exceptions = ScheduleException::query()->whereIn('practitioner_id', $schedules->pluck('practitioner_id')->unique())
            ->whereDate('date', $date)->get()->groupBy('practitioner_id');

        $minutes = [];

        foreach ($schedules as $schedule) {
            $start = self::minutesOf($schedule->start_time);
            $end = self::minutesOf($schedule->end_time);
            $available = max(0, $end - $start);

            foreach ($exceptions->get($schedule->practitioner_id, collect()) as $exception) {
                if ($exception->start_time === null || $exception->end_time === null) {
                    $available = 0;

                    break;
                }

                $overlap = min($end, self::minutesOf($exception->end_time)) - max($start, self::minutesOf($exception->start_time));
                $available -= max(0, $overlap);
            }

            $minutes[$schedule->practitioner_id] = ($minutes[$schedule->practitioner_id] ?? 0) + max(0, $available);
        }

        return $minutes;
    }

    /** Rata-rata produksi per kunjungan selesai 90 hari terakhir di cabang (dasar nilai janji yang hilang). */
    public static function averagePerVisit(int $branchId, Carbon $until): int
    {
        $from = $until->copy()->subDays(90)->toDateString();
        $base = fn () => DB::table('visits')->where('visits.branch_id', $branchId)
            ->whereIn('visits.status', [VisitStatus::Done->value, VisitStatus::Billed->value])
            ->whereDate('visits.visit_date', '>=', $from)->whereDate('visits.visit_date', '<=', $until->toDateString());

        $visits = (int) $base()->count();

        if ($visits === 0) {
            return 0;
        }

        $production = (int) $base()->join('invoices', 'invoices.visit_id', '=', 'visits.id')
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', InvoiceStatus::Void->value)
            ->sum(DB::raw('invoice_items.net_amount + invoice_items.package_value'));

        return (int) round($production / $visits);
    }

    private static function minutesOf(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return $h * 60 + $m;
    }
}
