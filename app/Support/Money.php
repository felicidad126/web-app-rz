<?php

namespace App\Support;

/** Format rupiah tanpa desimal: "Rp 1.365.000" atau "1.365.000". */
final class Money
{
    public static function format(?int $amount, bool $prefix = true): string
    {
        if ($amount === null) {
            return '—';
        }

        $formatted = number_format(abs($amount), 0, ',', '.');
        $sign = $amount < 0 ? '−' : '';

        return $prefix ? $sign.'Rp '.$formatted : $sign.$formatted;
    }
}
