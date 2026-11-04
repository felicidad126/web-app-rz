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

];
