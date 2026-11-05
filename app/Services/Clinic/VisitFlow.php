<?php

namespace App\Services\Clinic;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Alur kunjungan: check-in janji temu / datang langsung → di kursi → selesai.
 */
final class VisitFlow
{
    public static function checkIn(Appointment $appointment): Visit
    {
        if (! $appointment->canCheckIn()) {
            throw new InvalidArgumentException('Janji temu ini tidak bisa di-check-in (status atau tanggal tidak sesuai).');
        }

        return DB::transaction(function () use ($appointment): Visit {
            $appointment->update(['status' => AppointmentStatus::Arrived]);

            return Visit::create([
                'branch_id' => $appointment->branch_id,
                'patient_id' => $appointment->patient_id,
                'practitioner_id' => $appointment->practitioner_id,
                'chair_id' => $appointment->chair_id,
                'appointment_id' => $appointment->id,
                'payer_id' => $appointment->patient->default_payer_id,
                'visit_date' => today(),
                'status' => VisitStatus::Waiting,
                'chief_complaint' => $appointment->reason,
            ]);
        });
    }

    /** @param array{branch_id: int, patient_id: int, practitioner_id: int, chair_id?: int|null, payer_id?: int|null, chief_complaint?: string|null} $data */
    public static function walkIn(array $data): Visit
    {
        return Visit::create([
            ...$data,
            'visit_date' => today(),
            'status' => VisitStatus::Waiting,
            'is_walk_in' => true,
        ]);
    }

    public static function start(Visit $visit): void
    {
        if ($visit->status !== VisitStatus::Waiting) {
            throw new InvalidArgumentException('Hanya pasien yang menunggu yang bisa dipanggil ke kursi.');
        }

        $visit->update(['status' => VisitStatus::InTreatment, 'started_at' => now()]);
        $visit->ensureMedicalRecord();
    }

    public static function cancel(Visit $visit): void
    {
        if (! in_array($visit->status, [VisitStatus::Waiting, VisitStatus::InTreatment], true) || $visit->isLocked()) {
            throw new InvalidArgumentException('Kunjungan ini tidak bisa dibatalkan.');
        }

        $visit->update(['status' => VisitStatus::Cancelled, 'cancelled_at' => now()]);
    }
}
