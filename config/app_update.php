<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kunci Unggah Rilis
    |--------------------------------------------------------------------------
    |
    | Dikirim tooling rilis lokal sebagai header `X-App-Release-Key`. Bila
    | kosong, endpoint unggah mati total: tidak ada bypass tak sengaja saat
    | kunci belum di-set di server.
    |
    */

    'upload_key' => env('APP_RELEASE_UPLOAD_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Disk Object Storage
    |--------------------------------------------------------------------------
    |
    | Biner APK tidak boleh menempel di disk aplikasi supaya selamat dari
    | deploy; default-nya bucket GCS yang sama dengan asset customer.
    |
    */

    'disk' => env('APP_RELEASE_DISK', 'gcs'),

    /*
    |--------------------------------------------------------------------------
    | Batas Ukuran APK (kilobyte)
    |--------------------------------------------------------------------------
    */

    'max_kilobytes' => (int) env('APP_RELEASE_MAX_KILOBYTES', 204800),

];
