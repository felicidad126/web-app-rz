<?php

namespace App\Support;

use RuntimeException;

/**
 * HMAC deterministik untuk mencari data terenkripsi (mis. NIK) tanpa mendekripsi.
 */
final class BlindIndex
{
    public static function hash(?string $value): ?string
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            return null;
        }

        return hash_hmac('sha256', $normalized, self::key());
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }

    private static function key(): string
    {
        $key = config('clinic.blind_index_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('BLIND_INDEX_KEY belum diisi di .env.');
        }

        $decoded = base64_decode($key, strict: true);

        return $decoded === false ? $key : $decoded;
    }
}
