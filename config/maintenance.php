<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sakelar Maintenance TRIVA
    |--------------------------------------------------------------------------
    |
    | Kill switch seluruh sistem TRIVA: API mobile maupun halaman web publik.
    | Sumber utamanya `.env` di server production supaya bisa dinyalakan tanpa
    | deploy dan tanpa akses database — lihat `scripts/maintenance.sh`.
    |
    | Ini BUKAN `php artisan down` bawaan Laravel. `artisan down` mematikan
    | seluruh aplikasi termasuk back-office dan endpoint info aplikasi, jadi
    | klien tidak punya cara membaca pesan maintenance-nya.
    |
    | Bila `.env` tidak menyetel apa pun, nilai `app_configs.maintenance_mode`
    | di database tetap dipakai sebagai fallback (sakelar lama dari back-office).
    |
    */

    'enabled' => env('TRIVA_MAINTENANCE_MODE'),

    /*
    |--------------------------------------------------------------------------
    | Judul dan Pesan
    |--------------------------------------------------------------------------
    |
    | `message` dikirim sebagai `message` pada envelope error 503, dan tampil
    | apa adanya di halaman web. `title` hanya dipakai halaman web.
    |
    | Catatan soal aplikasi Android: build yang beredar saat ini memetakan 503
    | ke teks error generiknya sendiri, jadi user tetap terhalang memakai
    | aplikasi tapi belum melihat kalimat ini. Tetap tulis siap-tampil — build
    | berikutnya yang membaca `maintenance_*` dari `/api/v1/app/config` akan
    | menampilkannya utuh.
    |
    | `message` sengaja tanpa nilai default supaya `Maintenance::message()`
    | dapat membedakan "tidak di-set di .env" dari "di-set". Defaultnya ada di
    | `default_message` dan dipakai paling akhir, setelah database. Membaca
    | `env()` langsung di luar file config tidak bisa: nilainya null begitu
    | production menjalankan `config:cache`.
    |
    */

    'title' => env('TRIVA_MAINTENANCE_TITLE', 'Sistem Sedang Dalam Perawatan'),

    'message' => env('TRIVA_MAINTENANCE_MESSAGE'),

    'default_message' => 'TRIVA sedang dalam perawatan terjadwal. Silakan coba lagi beberapa saat lagi.',

    /*
    |--------------------------------------------------------------------------
    | Perkiraan Selesai
    |--------------------------------------------------------------------------
    |
    | Opsional, format apa pun yang dipahami `Carbon::parse` (disarankan
    | ISO-8601, contoh `2026-09-02T17:00:00+07:00`). Dipakai untuk teks
    | "diperkirakan selesai" di halaman web dan untuk menghitung `Retry-After`.
    | Nilai yang tidak bisa diparse diabaikan, bukan membuat request gagal.
    |
    */

    'until' => env('TRIVA_MAINTENANCE_UNTIL'),

    /*
    |--------------------------------------------------------------------------
    | Retry-After (detik)
    |--------------------------------------------------------------------------
    |
    | Dipakai bila `until` kosong. Header `Retry-After` mencegah klien dan
    | crawler menghajar server yang sedang diperbaiki.
    |
    */

    'retry_after' => (int) env('TRIVA_MAINTENANCE_RETRY_AFTER', 900),

    /*
    |--------------------------------------------------------------------------
    | Back-office Tetap Hidup
    |--------------------------------------------------------------------------
    |
    | Default `true`: `/admin` tetap bisa diakses saat maintenance supaya tim
    | ops masih bisa memeriksa data dan mematikan sakelarnya lewat back-office.
    | Set `false` hanya kalau memang ingin mengunci semuanya.
    |
    */

    'allow_admin' => (bool) env('TRIVA_MAINTENANCE_ALLOW_ADMIN', true),

    /*
    |--------------------------------------------------------------------------
    | IP yang Boleh Menembus
    |--------------------------------------------------------------------------
    |
    | Daftar IP dipisah koma, untuk verifikasi manual setelah perbaikan selesai
    | sebelum sakelar dimatikan untuk semua orang.
    |
    */

    'allow_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRIVA_MAINTENANCE_ALLOW_IPS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Path yang Selalu Terbuka
    |--------------------------------------------------------------------------
    |
    | Pola `Request::is()`. Endpoint info aplikasi HARUS ada di sini: klien
    | membacanya justru untuk mengetahui bahwa sistem sedang maintenance.
    | Health check dan webhook deploy juga wajib hidup supaya monitoring dan
    | deploy perbaikan tidak ikut mati.
    |
    */

    'except' => [
        'up',
        'api/v1/health',
        'api/v1/app/*',
        'api/deploy/github',
    ],

];
