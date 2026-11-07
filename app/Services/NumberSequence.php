<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Generator nomor urut yang aman dari race condition (row lock).
 *
 * Contoh: NumberSequence::next('rm') atau NumberSequence::next('invoice', 'JKT-202611').
 */
final class NumberSequence
{
    public static function next(string $key, string $scope = ''): int
    {
        return DB::transaction(function () use ($key, $scope): int {
            DB::table('number_sequences')->insertOrIgnore([
                'key' => $key,
                'scope' => $scope,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('number_sequences')
                ->where('key', $key)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            $next = ((int) $row->last_number) + 1;

            DB::table('number_sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });
    }

    /** Nomor kunjungan, mis. KJ-2611-0047 (urut per bulan). */
    public static function nextVisitNumber(\DateTimeInterface $date): string
    {
        $period = $date->format('ym');

        return 'KJ-'.$period.'-'.str_pad((string) self::next('visit', $period), 4, '0', STR_PAD_LEFT);
    }

    /** Nomor tagihan per cabang per bulan, mis. KMG-2611-0021. */
    public static function nextInvoiceNumber(string $branchCode, \DateTimeInterface $date): string
    {
        $scope = strtoupper($branchCode).'-'.$date->format('ym');

        return $scope.'-'.str_pad((string) self::next('invoice', $scope), 4, '0', STR_PAD_LEFT);
    }

    /** Nomor paket pasien, mis. PKT-2604-0007. */
    public static function nextPackageNumber(\DateTimeInterface $date): string
    {
        $period = $date->format('ym');

        return 'PKT-'.$period.'-'.str_pad((string) self::next('package', $period), 4, '0', STR_PAD_LEFT);
    }

    /** Nomor dokumen stok per cabang per bulan, mis. PNR-KMG-2611-0003 (awalan = jenis dokumen). */
    public static function nextStockNumber(string $prefix, string $branchCode, \DateTimeInterface $date): string
    {
        $scope = $prefix.'-'.strtoupper($branchCode).'-'.$date->format('ym');

        return $scope.'-'.str_pad((string) self::next('stock', $scope), 4, '0', STR_PAD_LEFT);
    }

    /** Nomor permintaan pembelian per cabang per bulan, mis. PMT-KMG-2611-0004. */
    public static function nextPurchaseRequestNumber(string $branchCode, \DateTimeInterface $date): string
    {
        return self::nextStockNumber('PMT', $branchCode, $date);
    }

    /** Nomor pesanan pembelian per cabang per bulan, mis. PSN-KMG-2611-0002. */
    public static function nextPurchaseOrderNumber(string $branchCode, \DateTimeInterface $date): string
    {
        return self::nextStockNumber('PSN', $branchCode, $date);
    }

    /** Nomor order lab, mis. LAB-2611-0012. */
    public static function nextLabOrderNumber(\DateTimeInterface $date): string
    {
        $period = $date->format('ym');

        return 'LAB-'.$period.'-'.str_pad((string) self::next('lab', $period), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Majukan urutan minimal ke $number (tidak pernah mundur). Dipakai saat nomor diisi dari data lama.
     */
    public static function advanceTo(string $key, int $number, string $scope = ''): void
    {
        DB::transaction(function () use ($key, $scope, $number): void {
            DB::table('number_sequences')->insertOrIgnore([
                'key' => $key,
                'scope' => $scope,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('number_sequences')
                ->where('key', $key)
                ->where('scope', $scope)
                ->where('last_number', '<', $number)
                ->update(['last_number' => $number, 'updated_at' => now()]);
        });
    }

    /**
     * No. RM yang diisi manual/migrasi dengan pola awalan + angka (mis. MR000123) memajukan urutan RM,
     * agar pasien baru tidak mendapat nomor yang sudah terpakai.
     */
    public static function reserveRmNumber(string $rm): void
    {
        $prefix = preg_quote((string) config('clinic.rm_number.prefix'), '/');

        if (preg_match('/^'.$prefix.'(\d+)$/', $rm, $m)) {
            self::advanceTo('rm', (int) $m[1]);
        }
    }

    public static function nextRmNumber(): string
    {
        $number = self::next('rm');

        return config('clinic.rm_number.prefix')
            .str_pad((string) $number, (int) config('clinic.rm_number.pad'), '0', STR_PAD_LEFT);
    }
}
