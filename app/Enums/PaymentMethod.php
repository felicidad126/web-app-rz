<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Metode pembayaran. Deposit memotong saldo deposit pasien, bukan uang masuk laci. */
enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Transfer = 'transfer';
    case Debit = 'debit';
    case CreditCard = 'credit_card';
    case Deposit = 'deposit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Qris => 'QRIS',
            self::Transfer => 'Transfer',
            self::Debit => 'Debit',
            self::CreditCard => 'Kartu kredit',
            self::Deposit => 'Deposit',
        };
    }

    /** Dicocokkan dengan apa saat tutup kasir. */
    public function reconciliationSource(): string
    {
        return match ($this) {
            self::Cash => 'Hitung fisik laci',
            self::Qris => 'Laporan merchant QRIS',
            self::Transfer => 'Mutasi rekening',
            self::Debit, self::CreditCard => 'Settlement EDC',
            self::Deposit => '—',
        };
    }

    /** Metode yang uangnya benar-benar diterima kasir (masuk rekap sesi kasir). */
    public function isMoneyIn(): bool
    {
        return $this !== self::Deposit;
    }

    /** @return list<self> */
    public static function moneyIn(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method): bool => $method->isMoneyIn()));
    }

    /** @return array<string, string> */
    public static function moneyInOptions(): array
    {
        return collect(self::moneyIn())->mapWithKeys(fn (self $method): array => [$method->value => $method->getLabel()])->all();
    }
}
