<?php

namespace App\Services\Clinic;

use App\Models\Appointment;
use App\Models\PractitionerSchedule;
use App\Models\ScheduleException;
use Illuminate\Support\Carbon;

/**
 * Cek bentrok janji temu (dokter, kursi, pasien) dan kesesuaian dengan jadwal praktik.
 */
final class AppointmentAvailability
{
    /**
     * Pesan bentrok. Kosong = tidak bentrok.
     *
     * @return list<string>
     */
    public static function conflicts(
        int $practitionerId,
        ?int $chairId,
        int $patientId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreAppointmentId = null,
    ): array {
        $base = fn () => Appointment::query()
            ->active()
            ->overlapping($start, $end)
            ->when($ignoreAppointmentId, fn ($q) => $q->whereKeyNot($ignoreAppointmentId))
            ->with('patient:id,name');

        $messages = [];

        if ($hit = $base()->where('practitioner_id', $practitionerId)->first()) {
            $messages[] = 'Dokter sudah ada janji '.self::range($hit).' dengan '.$hit->patient->name.'.';
        }

        if ($chairId && ($hit = $base()->where('chair_id', $chairId)->first())) {
            $messages[] = 'Kursi sudah dipakai '.self::range($hit).' oleh '.$hit->patient->name.'.';
        }

        if ($hit = $base()->where('patient_id', $patientId)->first()) {
            $messages[] = 'Pasien sudah punya janji lain '.self::range($hit).'.';
        }

        return $messages;
    }

    /** Pesan jika di luar jadwal praktik atau bertepatan dengan cuti. Null = sesuai jadwal. */
    public static function scheduleIssue(int $practitionerId, int $branchId, Carbon $start, Carbon $end): ?string
    {
        $exception = ScheduleException::query()
            ->where('practitioner_id', $practitionerId)
            ->whereDate('date', $start->toDateString())
            ->get()
            ->first(fn (ScheduleException $e): bool => $e->isWholeDay()
                || ($e->start_time < $end->format('H:i:s') && $e->end_time > $start->format('H:i:s')));

        if ($exception) {
            return 'Dokter tidak praktik pada waktu ini: '.$exception->reason.'.';
        }

        $covered = PractitionerSchedule::query()
            ->active()
            ->where('practitioner_id', $practitionerId)
            ->where('branch_id', $branchId)
            ->where('day_of_week', $start->dayOfWeekIso)
            ->where('start_time', '<=', $start->format('H:i:s'))
            ->where('end_time', '>=', $end->format('H:i:s'))
            ->exists();

        return $covered ? null : 'Di luar jadwal praktik dokter di cabang ini.';
    }

    private static function range(Appointment $appointment): string
    {
        return $appointment->start_at->format('H:i').'–'.$appointment->end_at->format('H:i');
    }
}
