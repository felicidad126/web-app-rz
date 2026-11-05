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

    public static function nextRmNumber(): string
    {
        $number = self::next('rm');

        return config('clinic.rm_number.prefix')
            .str_pad((string) $number, (int) config('clinic.rm_number.pad'), '0', STR_PAD_LEFT);
    }
}
