<?php

namespace App\Services\Lab;

use App\Enums\AppointmentStatus;
use App\Enums\LabOrderStatus;
use App\Models\Appointment;
use App\Models\LabOrder;
use App\Models\User;
use App\Models\Visit;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Alur order lab: Dibuat → Dikirim ke lab → Diterima klinik → Dicek dokter → Dipasang (atau Batal).
 * Biaya lab ikut di-snapshot ke tagihan saat terbit (InvoiceBuilder) untuk margin & jasa dokter.
 */
final class LabOrders
{
    /** @param array<string, mixed> $data */
    public static function create(array $data, User $user): LabOrder
    {
        if (filled($data['visit_id'] ?? null)) {
            $visit = Visit::findOrFail($data['visit_id']);

            if ($visit->patient_id !== (int) $data['patient_id']) {
                throw new InvalidArgumentException('Kunjungan yang dipilih bukan milik pasien ini.');
            }

            $data['branch_id'] ??= $visit->branch_id;
        }

        if (blank($data['branch_id'] ?? null)) {
            throw new InvalidArgumentException('Pilih cabang.');
        }

        return DB::transaction(function () use ($data, $user): LabOrder {
            $order = new LabOrder([
                ...$data,
                'number' => NumberSequence::nextLabOrderNumber(now()),
                'status' => LabOrderStatus::Created,
                'cost' => (int) ($data['cost'] ?? 0),
            ]);
            $order->forceFill(['created_by' => $user->id, 'updated_by' => $user->id])->save();

            return $order;
        });
    }

    public static function advance(LabOrder $order, User $user): LabOrder
    {
        $next = $order->status->next();

        if ($next === null) {
            throw new InvalidArgumentException('Order ini sudah '.$order->status->getLabel().'.');
        }

        $attributes = ['status' => $next];

        if (($column = $next->timestampColumn()) !== null) {
            $attributes[$column] = now();
        }

        $order->forceFill([...$attributes, 'updated_by' => $user->id])->save();

        return $order;
    }

    public static function cancel(LabOrder $order, string $reason, User $user): void
    {
        if (! in_array($order->status, LabOrderStatus::active(), true)) {
            throw new InvalidArgumentException('Order yang sudah '.$order->status->getLabel().' tidak bisa dibatalkan.');
        }

        if (mb_strlen(trim($reason)) < 5) {
            throw new InvalidArgumentException('Tulis alasan yang jelas (minimal 5 karakter).');
        }

        $order->forceFill(['status' => LabOrderStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => trim($reason), 'updated_by' => $user->id])->save();
    }

    /** Subquery janji temu berikutnya milik pasien (untuk kolom "Janji pasang"). */
    public static function withNextAppointment(Builder $query): Builder
    {
        return $query->addSelect([
            'next_appointment_at' => Appointment::query()->select('start_at')
                ->whereColumn('appointments.patient_id', 'lab_orders.patient_id')
                ->whereIn('status', [AppointmentStatus::Booked->value, AppointmentStatus::Confirmed->value])
                ->where('start_at', '>=', now()->startOfDay())
                ->orderBy('start_at')->limit(1),
        ])->withCasts(['next_appointment_at' => 'datetime']);
    }

    /** Urutan mockup: terlambat dulu, lalu jatuh tempo terdekat. */
    public static function orderByUrgency(Builder $query): Builder
    {
        return $query
            ->orderByRaw('case when lab_orders.status in (?, ?) and lab_orders.due_at < ? then 0 else 1 end', [
                LabOrderStatus::Created->value, LabOrderStatus::Sent->value, today()->toDateString(),
            ])
            ->orderByRaw('lab_orders.due_at is null')
            ->orderBy('lab_orders.due_at')
            ->orderBy('lab_orders.id');
    }
}
