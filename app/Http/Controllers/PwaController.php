<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Manifest PWA & halaman offline. Isinya statis (tanpa data pasien/pengguna); nama aplikasi dari
 * config('app.name') / config('app.short_name') supaya mengganti nama cukup lewat .env.
 */
class PwaController
{
    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name'),
            'short_name' => config('app.short_name'),
            'description' => 'Sistem manajemen klinik gigi',
            'lang' => 'id',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#fef8e8', // krem logo klinik (layar pembuka PWA)
            'theme_color' => '#0d6e63',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function offline(): Response
    {
        return response()->view('pwa.offline')->header('Cache-Control', 'public, max-age=3600');
    }
}
