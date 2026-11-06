<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis mutasi stok. */
enum StockMovementType: string implements HasColor, HasLabel
{
    case Receipt = 'receipt';
    case Usage = 'usage';
    case Dispensing = 'dispensing';
    case Sale = 'sale';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Disposal = 'disposal';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Receipt => 'Penerimaan',
            self::Usage => 'Pemakaian klinis',
            self::Dispensing => 'Resep diserahkan',
            self::Sale => 'Penjualan',
            self::TransferOut => 'Transfer keluar',
            self::TransferIn => 'Transfer masuk',
            self::Disposal => 'Pemusnahan',
            self::Adjustment => 'Koreksi opname',
            self::Reversal => 'Pembatalan mutasi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Receipt => 'gray',
            self::Usage => 'gray',
            self::Dispensing => 'gray',
            self::Sale => 'gray',
            self::TransferOut => 'gray',
            self::TransferIn => 'gray',
            self::Disposal => 'gray',
            self::Adjustment => 'warning',
            self::Reversal => 'warning',
        };
    }

    /** Jenis yang perlu persetujuan sebelum tercatat. */
    public function needsApproval(): bool
    {
        return in_array($this, [self::Disposal, self::Adjustment], true);
    }

    /** Awalan nomor dokumen. */
    public function prefix(): string
    {
        return match ($this) {
            self::Receipt => 'PNR',
            self::Usage, self::Dispensing, self::Sale => 'KLR',
            self::TransferOut, self::TransferIn => 'TRF',
            self::Disposal => 'MSN',
            self::Adjustment => 'KOR',
            self::Reversal => 'BTL',
        };
    }
}
