<?php

namespace App\Filament\Clinic;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\Visit;
use Illuminate\Support\HtmlString;

/**
 * Status janji/kunjungan sebagai titik + kata (Fondasi UI): Terjadwal, Dikonfirmasi, Datang, Di kursi, Selesai, Tidak datang.
 * Satu sumber warna untuk papan Jadwal, dashboard dokter, dan halaman pemeriksaan.
 */
final class StatusDot
{
    public const SCHEDULED = '#a1a1aa';

    public const CONFIRMED = '#175cd3';

    public const ARRIVED = '#dc6803';

    public const IN_CHAIR = '#7a5af8';

    public const DONE = '#079455';

    public const NO_SHOW = '#d92d20';

    /** @return list<array{0: string, 1: string}> warna & label untuk legenda */
    public static function legend(): array
    {
        return [
            [self::SCHEDULED, 'Terjadwal'],
            [self::CONFIRMED, 'Dikonfirmasi'],
            [self::ARRIVED, 'Datang'],
            [self::IN_CHAIR, 'Di kursi'],
            [self::DONE, 'Selesai'],
            [self::NO_SHOW, 'Tidak datang'],
        ];
    }

    /**
     * Status satu janji, memperhitungkan kunjungannya (relasi `visit` harus sudah dimuat).
     *
     * @return array{0: string, 1: string} warna & label (dengan jam bila $withTime)
     */
    public static function forAppointment(Appointment $appointment, bool $withTime = false): array
    {
        $visit = $appointment->visit;
        $time = fn (?\DateTimeInterface $at): string => $withTime && $at !== null ? ' '.$at->format('H:i') : '';

        if ($appointment->status === AppointmentStatus::Cancelled) {
            return [self::SCHEDULED, 'Dibatalkan'];
        }

        if ($visit !== null && $visit->status === VisitStatus::InTreatment) {
            return [self::IN_CHAIR, 'Di kursi'.$time($visit->started_at)];
        }

        if ($visit !== null && in_array($visit->status, [VisitStatus::Done, VisitStatus::Billed], true)) {
            return [self::DONE, 'Selesai'.$time($visit->finished_at)];
        }

        return match (true) {
            $appointment->status === AppointmentStatus::Arrived => [self::ARRIVED, 'Datang'.$time($visit?->arrived_at)],
            $appointment->status === AppointmentStatus::Confirmed => [self::CONFIRMED, 'Dikonfirmasi'],
            $appointment->status === AppointmentStatus::NoShow => [self::NO_SHOW, 'Tidak datang'],
            default => [self::SCHEDULED, 'Terjadwal'],
        };
    }

    /** @return array{0: string, 1: string} */
    public static function forVisit(Visit $visit): array
    {
        return match ($visit->status) {
            VisitStatus::Waiting => [self::ARRIVED, 'Datang'],
            VisitStatus::InTreatment => [self::IN_CHAIR, 'Di kursi'],
            VisitStatus::Done, VisitStatus::Billed => [self::DONE, $visit->status->getLabel()],
            VisitStatus::Cancelled => [self::SCHEDULED, $visit->status->getLabel()],
        };
    }

    public static function html(string $color, string $label): HtmlString
    {
        return new HtmlString(
            '<span style="display: inline-flex; align-items: center; gap: 6px; white-space: nowrap">'
            .'<span aria-hidden="true" style="width: 8px; height: 8px; border-radius: 50%; flex: none; background: '.e($color).'"></span>'
            .e($label).'</span>'
        );
    }
}
