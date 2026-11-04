<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Pilihan kategori tindakan. */
enum TreatmentCategory: string implements HasLabel
{
    case Consultation = 'consultation';
    case Preventive = 'preventive';
    case Restorative = 'restorative';
    case Endodontic = 'endodontic';
    case Periodontic = 'periodontic';
    case Surgery = 'surgery';
    case Prosthodontic = 'prosthodontic';
    case Orthodontic = 'orthodontic';
    case Pediatric = 'pediatric';
    case Aesthetic = 'aesthetic';
    case Radiology = 'radiology';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Consultation => 'Konsultasi & Pemeriksaan',
            self::Preventive => 'Preventif',
            self::Restorative => 'Restorasi / Tambal',
            self::Endodontic => 'Endodontik / Saluran Akar',
            self::Periodontic => 'Periodontik',
            self::Surgery => 'Bedah / Pencabutan',
            self::Prosthodontic => 'Prostodontik / Gigi Tiruan',
            self::Orthodontic => 'Ortodontik',
            self::Pediatric => 'Kedokteran Gigi Anak',
            self::Aesthetic => 'Estetik',
            self::Radiology => 'Radiologi',
            self::Other => 'Lainnya',
        };
    }
}
