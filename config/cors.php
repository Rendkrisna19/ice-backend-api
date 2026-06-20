<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://0295-182-9-48-137.ngrok-free.app',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://zadify.id',
        'https://app.zadify.id',
        'https://www.zadify.id',
        'https://ice-web-suite.vercel.app',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /* | Ubah ke true jika Anda menggunakan Laravel Sanctum untuk autentikasi
    | berbasis cookie/session agar login tetap terjaga.
    */
    'supports_credentials' => true,

];
