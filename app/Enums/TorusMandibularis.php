<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TorusMandibularis: string implements HasLabel
{
    case None = 'none';
    case Left = 'left';
    case Right = 'right';
    case Both = 'both';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Tidak ada',
            self::Left => 'Sisi kiri',
            self::Right => 'Sisi kanan',
            self::Both => 'Kedua sisi',
        };
    }
}
