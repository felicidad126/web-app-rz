<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status pesanan pembelian ke supplier. */
enum PurchaseOrderStatus: string implements HasColor, HasLabel
{
    case Ordered = 'ordered';
    case Partial = 'partial';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ordered => 'Menunggu datang',
            self::Partial => 'Diterima sebagian',
            self::Received => 'Diterima lengkap',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ordered, self::Partial => 'warning',
            self::Received => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Ordered, self::Partial], true);
    }
}
