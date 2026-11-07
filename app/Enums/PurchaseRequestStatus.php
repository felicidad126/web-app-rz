<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status permintaan pembelian dari perawat/staf. */
enum PurchaseRequestStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Ordered = 'ordered';
    case Received = 'received';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::Ordered => 'Dipesan',
            self::Received => 'Diterima',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Kuning = perlu tindakan (disetujui / dipesan); hijau = barang sudah datang; dipesan = menunggu pesanannya (netral). */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending, self::Approved => 'warning',
            self::Received => 'success',
            self::Ordered, self::Rejected, self::Cancelled => 'gray',
        };
    }

    /** @return list<self> masih terbuka (belum selesai, belum ditolak/dibatalkan) */
    public static function open(): array
    {
        return [self::Pending, self::Approved, self::Ordered];
    }
}
