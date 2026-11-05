<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blind index
    |--------------------------------------------------------------------------
    |
    | NIK disimpan terenkripsi. Untuk pencarian dan cek duplikat, disimpan juga
    | HMAC-SHA256 dari NIK dengan kunci ini. Kunci TIDAK BOLEH diganti setelah
    | ada data, karena hash lama tidak akan cocok lagi.
    |
    */

    'blind_index_key' => env('BLIND_INDEX_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Nomor Rekam Medis
    |--------------------------------------------------------------------------
    |
    | Nomor RM unik untuk seluruh klinik (lintas cabang).
    | Contoh dengan prefix "RM-" dan pad 6: RM-000001.
    |
    */

    'rm_number' => [
        'prefix' => env('CLINIC_RM_PREFIX', ''),
        'pad' => (int) env('CLINIC_RM_PAD', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Peringatan masa berlaku SIP
    |--------------------------------------------------------------------------
    */

    'sip_expiry_warning_days' => 60,

    /*
    |--------------------------------------------------------------------------
    | Kasir & billing
    |--------------------------------------------------------------------------
    |
    | rounding: pembulatan total tagihan ke kelipatan terdekat (0 = tanpa, 100, 1000).
    | denominations: pecahan uang untuk hitung tunai saat tutup kasir.
    |
    */

    'billing' => [
        'rounding' => (int) env('CLINIC_BILLING_ROUNDING', 0),
        'denominations' => [100_000, 50_000, 20_000, 10_000, 5_000, 2_000, 1_000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password akun demo (DemoSeeder)
    |--------------------------------------------------------------------------
    |
    | Default "password" hanya untuk laptop developer. Di staging yang bisa
    | diakses dari internet WAJIB diisi acak (deploy/provision.sh mengisinya).
    |
    */

    'demo_password' => env('DEMO_PASSWORD') ?: 'password',

];
