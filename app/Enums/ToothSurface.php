<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Permukaan gigi (Panduan RM Kedokteran Gigi): satu huruf per permukaan. */
enum ToothSurface: string implements HasLabel
{
    case Mesial = 'M';
    case Occlusal = 'O';
    case Distal = 'D';
    case Vestibular = 'V';
    case Lingual = 'L';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mesial => 'M · mesial',
            self::Occlusal => 'O · oklusal/insisal',
            self::Distal => 'D · distal',
            self::Vestibular => 'V · bukal/labial',
            self::Lingual => 'L · lingual/palatal',
        };
    }

    /** Urutan baku penulisan permukaan, mis. "MOD". */
    public static function normalize(?string $surfaces): ?string
    {
        if (blank($surfaces)) {
            return null;
        }

        $chars = array_unique(str_split(strtoupper(preg_replace('/[^A-Za-z]/', '', $surfaces) ?? '')));
        $order = array_map(fn (self $s): string => $s->value, self::cases());
        $valid = array_values(array_filter($order, fn (string $s): bool => in_array($s, $chars, true)));

        return $valid === [] ? null : implode('', $valid);
    }
}
