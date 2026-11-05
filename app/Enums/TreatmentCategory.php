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

    /** @return array{0: string, 1: string} Warna kartu di papan jadwal: latar, aksen. */
    public function tint(): array
    {
        return match ($this) {
            self::Consultation, self::Radiology, self::Other => ['#f4f4f5', '#a1a1aa'],
            self::Preventive, self::Periodontic => ['#ecfdf3', '#47cd89'],
            self::Restorative, self::Pediatric => ['#eff8ff', '#53b1fd'],
            self::Endodontic => ['#eef4ff', '#8098f9'],
            self::Surgery => ['#fdf2fa', '#ee46bc'],
            self::Prosthodontic, self::Aesthetic => ['#f4f3ff', '#9b8afb'],
            self::Orthodontic => ['#fffaeb', '#fdb022'],
        };
    }
}
