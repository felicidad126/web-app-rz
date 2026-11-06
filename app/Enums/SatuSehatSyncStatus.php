<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status pengiriman ke SATUSEHAT. */
enum SatuSehatSyncStatus: string implements HasColor, HasLabel
{
    /** Menunggu giliran di antrean. */
    case Pending = 'pending';

    /** Diterima SATUSEHAT. */
    case Sent = 'sent';

    /** Gagal karena teknis (API down, timeout, ditolak). Dicoba ulang otomatis. */
    case Failed = 'failed';

    /** Tidak bisa dikirim sampai data diperbaiki (mis. NIK kosong, kode ICD-9-CM belum dipetakan). */
    case Blocked = 'blocked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Antre',
            self::Sent => 'Diterima',
            self::Failed => 'Gagal kirim',
            self::Blocked => 'Perlu diperbaiki',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Blocked => 'warning',
        };
    }
}
