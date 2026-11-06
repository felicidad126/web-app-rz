<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bridging SATUSEHAT (Kemenkes, FHIR R4)
    |--------------------------------------------------------------------------
    |
    | Kredensial diambil dari SATUSEHAT Platform (menu kredensial organisasi).
    | Disimpan di .env server, bukan di database atau repositori.
    |
    | enabled: kirim otomatis setelah rekam medis ditandatangani.
    | environment: "sandbox" (staging Kemenkes) atau "production".
    |
    */

    'enabled' => (bool) env('SATUSEHAT_ENABLED', false),

    'environment' => env('SATUSEHAT_ENV', 'sandbox'),

    'organization_id' => env('SATUSEHAT_ORGANIZATION_ID'),

    'client_id' => env('SATUSEHAT_CLIENT_ID'),

    'client_secret' => env('SATUSEHAT_CLIENT_SECRET'),

    'environments' => [
        'sandbox' => [
            'auth_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1',
            'fhir_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1',
        ],
        'production' => [
            'auth_url' => 'https://api-satusehat.kemkes.go.id/oauth2/v1',
            'fhir_url' => 'https://api-satusehat.kemkes.go.id/fhir-r4/v1',
        ],
    ],

    // Opsional: timpa URL bila Kemenkes mengubah alamat API.
    'auth_url' => env('SATUSEHAT_AUTH_URL'),

    'fhir_url' => env('SATUSEHAT_FHIR_URL'),

    'timeout' => (int) env('SATUSEHAT_TIMEOUT', 30),

    'queue' => env('SATUSEHAT_QUEUE', 'default'),

];
