<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis lampiran rekam medis. */
enum AttachmentType: string implements HasColor, HasLabel
{
    case XrayPeriapical = 'xray_periapical';
    case XrayPanoramic = 'xray_panoramic';
    case PhotoIntraoral = 'photo_intraoral';
    case PhotoExtraoral = 'photo_extraoral';
    case Document = 'document';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::XrayPeriapical => 'Rontgen periapikal',
            self::XrayPanoramic => 'Rontgen panoramik',
            self::PhotoIntraoral => 'Foto intraoral',
            self::PhotoExtraoral => 'Foto ekstraoral',
            self::Document => 'Dokumen',
            self::Other => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::XrayPeriapical, self::XrayPanoramic => 'info',
            self::PhotoIntraoral, self::PhotoExtraoral, self::Document, self::Other => 'gray',
        };
    }
}
