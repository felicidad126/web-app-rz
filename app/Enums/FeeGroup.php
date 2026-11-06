<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis jasa (kelompok bagi hasil dokter), terpisah dari kategori klinis tindakan. */
enum FeeGroup: string implements HasColor, HasLabel
{
    case Consultation = 'consultation';
    case General = 'general';
    case LabWork = 'lab_work';
    case Orthodontic = 'orthodontic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Consultation => 'Konsultasi',
            self::General => 'Tindakan umum',
            self::LabWork => 'Tindakan dengan lab (prostodonti)',
            self::Orthodontic => 'Ortodonti',
        };
    }

    public function getColor(): string
    {
        return 'gray';
    }

    /** Jenis jasa awal dari kategori klinis tindakan. */
    public static function fromCategory(TreatmentCategory $category): self
    {
        return match ($category) {
            TreatmentCategory::Consultation => self::Consultation,
            TreatmentCategory::Prosthodontic => self::LabWork,
            TreatmentCategory::Orthodontic => self::Orthodontic,
            default => self::General,
        };
    }
}
