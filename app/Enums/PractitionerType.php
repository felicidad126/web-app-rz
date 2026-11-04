<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Pilihan jenis tenaga kesehatan. */
enum PractitionerType: string implements HasLabel
{
    case Dentist = 'dentist';
    case DentalSpecialist = 'dental_specialist';
    case DentalTherapist = 'dental_therapist';
    case Nurse = 'nurse';
    case GeneralPractitioner = 'general_practitioner';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dentist => 'Dokter Gigi',
            self::DentalSpecialist => 'Dokter Gigi Spesialis',
            self::DentalTherapist => 'Terapis Gigi dan Mulut',
            self::Nurse => 'Perawat',
            self::GeneralPractitioner => 'Dokter Umum',
        };
    }
}
