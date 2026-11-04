<?php

$proxies = env('TRUSTED_PROXIES');

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Isi jika aplikasi berada di balik reverse proxy / Cloudflare / load
    | balancer yang meneruskan header X-Forwarded-*, agar Laravel tahu request
    | aslinya HTTPS (URL aset, cookie secure, PWA). Contoh: "10.0.0.1,10.0.0.2"
    | atau "*" (semua; hanya jika server tidak bisa diakses langsung dari luar).
    | Kosongkan jika Nginx + PHP-FPM berjalan langsung di server yang sama.
    |
    */

    'proxies' => match (true) {
        $proxies === null || $proxies === '' => null,
        $proxies === '*' => '*',
        default => array_map('trim', explode(',', $proxies)),
    },

];
