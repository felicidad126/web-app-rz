<?php

namespace App\Services\Commission;

use App\Enums\AttendanceType;
use App\Enums\PractitionerType;
use App\Enums\VisitStatus;
use App\Models\Practitioner;
use App\Models\PractitionerAttendance;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/** Uang hadir / on call dokter. Nominal diambil dari tarif dokter saat dicatat (bisa diubah per catatan). */
final class Attendances
{
    public static function record(Practitioner $practitioner, int $branchId, Carbon $date, AttendanceType $type, ?int $amount, User $user, ?string $notes = null): PractitionerAttendance
    {
        $practitioner->loadMissing('user');
        $existing = PractitionerAttendance::query()->where('practitioner_id', $practitioner->id)->where('branch_id', $branchId)
            ->whereDate('date', $date)->where('type', $type)->first();

        if ($existing !== null) {
            throw new InvalidArgumentException($practitioner->displayName().' sudah tercatat '.$type->getLabel().' pada '.$date->format('d/m/Y').'.');
        }

        return PractitionerAttendance::create([
            'practitioner_id' => $practitioner->id,
            'branch_id' => $branchId,
            'date' => $date->toDateString(),
            'type' => $type,
            'amount' => $amount ?? self::defaultAmount($practitioner, $type),
            'notes' => $notes,
        ]);
    }

    /** Catat "Hadir" untuk setiap dokter yang punya kunjungan selesai di cabang & tanggal itu. */
    public static function fillFromVisits(int $branchId, Carbon $date, User $user): int
    {
        $practitionerIds = Visit::query()->where('branch_id', $branchId)->whereDate('visit_date', $date)
            ->whereIn('status', [VisitStatus::Done, VisitStatus::Billed])->distinct()->pluck('practitioner_id');

        $count = 0;

        Practitioner::query()->whereIn('id', $practitionerIds)
            ->whereIn('type', [PractitionerType::Dentist, PractitionerType::DentalSpecialist])
            ->with('user')->get()
            ->each(function (Practitioner $practitioner) use ($branchId, $date, $user, &$count): void {
                try {
                    self::record($practitioner, $branchId, $date, AttendanceType::Present, null, $user, 'Dari kunjungan');
                    $count++;
                } catch (InvalidArgumentException) {
                    // Sudah tercatat.
                }
            });

        return $count;
    }

    public static function defaultAmount(Practitioner $practitioner, AttendanceType $type): int
    {
        return $type === AttendanceType::OnCall ? $practitioner->on_call_fee : $practitioner->attendance_fee;
    }
}
