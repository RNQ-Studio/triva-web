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
    | Disk Penyimpanan Rilis
    |--------------------------------------------------------------------------
    |
    | Default-nya `public`: berkasnya berada di storage/app/public, jadi
    | selamat dari deploy dan langsung terunduh lewat APP_URL/storage tanpa
    | kredensial tambahan. Tidak ada environment proyek ini yang punya
    | kredensial GCS, jadi default ke `gcs` hanya menghasilkan kegagalan tulis
    | "Not Found" saat rilis diunggah.
    |
    | Object storage tetap lebih baik karena biner tidak ikut hilang saat
    | server dibangun ulang. Set APP_RELEASE_DISK=gcs begitu bucket dan service
    | account-nya tersedia.
    |
    */

    'disk' => env('APP_RELEASE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Batas Ukuran APK (kilobyte)
    |--------------------------------------------------------------------------
    */

    'max_kilobytes' => (int) env('APP_RELEASE_MAX_KILOBYTES', 204800),

];
