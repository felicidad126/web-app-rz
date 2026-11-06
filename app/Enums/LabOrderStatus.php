<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Tahap order lab (mockup: Dibuat → Dikirim ke lab → Diterima klinik → Dicek dokter → Dipasang). */
enum LabOrderStatus: string implements HasColor, HasLabel
{
    case Created = 'created';
    case Sent = 'sent';
    case Received = 'received';
    case Checked = 'checked';
    case Fitted = 'fitted';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Dibuat',
            self::Sent => 'Dikirim ke lab',
            self::Received => 'Diterima klinik',
            self::Checked => 'Dicek dokter',
            self::Fitted => 'Dipasang',
            self::Cancelled => 'Batal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Created => 'gray',
            self::Sent => 'info',
            self::Received => 'warning',
            self::Checked => 'warning',
            self::Fitted => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Urutan tahap untuk progres (batal tidak punya urutan). */
    public function step(): int
    {
        return match ($this) {
            self::Created => 1,
            self::Sent => 2,
            self::Received => 3,
            self::Checked => 4,
            self::Fitted => 5,
            self::Cancelled => 0,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Created => self::Sent,
            self::Sent => self::Received,
            self::Received => self::Checked,
            self::Checked => self::Fitted,
            default => null,
        };
    }

    /** Label tombol untuk maju ke tahap ini. */
    public function advanceLabel(): string
    {
        return match ($this) {
            self::Sent => 'Tandai dikirim ke lab',
            self::Received => 'Tandai diterima klinik',
            self::Checked => 'Tandai sudah dicek dokter',
            self::Fitted => 'Tandai sudah dipasang',
            default => $this->getLabel(),
        };
    }

    /** Kolom waktu yang diisi saat order masuk ke tahap ini. */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Sent => 'sent_at',
            self::Received => 'received_at',
            self::Checked => 'checked_at',
            self::Fitted => 'fitted_at',
            self::Cancelled => 'cancelled_at',
            default => null,
        };
    }

    /** @return list<self> tahap berjalan (bukan selesai/batal) */
    public static function active(): array
    {
        return [self::Created, self::Sent, self::Received, self::Checked];
    }
}
