<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Salinan email notifikasi
    |--------------------------------------------------------------------------
    |
    | Setiap notifikasi in-app/push TRIVA (pelanggan maupun admin) juga dikirim
    | sebagai email ke alamat berikut. Kosongkan NOTIFICATION_EMAIL_COPY untuk
    | menonaktifkan fitur tanpa deploy. Pisahkan beberapa alamat dengan koma.
    | Transport email mengikuti config/mail.php (produksi: SMTP Gmail + App
    | Password).
    |
    */

    'copy_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('NOTIFICATION_EMAIL_COPY', ''))
    ))),

    // Queue yang diproses worker production (Supervisor triva-web-worker).
    'queue' => env('NOTIFICATION_EMAIL_COPY_QUEUE', env('REDIS_QUEUE', 'triva')),

    // Zona waktu yang ditampilkan pada email.
    'timezone' => env('NOTIFICATION_EMAIL_TIMEZONE', 'Asia/Jakarta'),

    'type_labels' => [
        'appraisal_result_ready' => 'Hasil Appraisal',
        'toyota_service_booking' => 'Booking Servis Toyota',
        'otoxpert_booking' => 'Booking OtoXpert',
        'credit_simulation' => 'Simulasi Kredit',
        'body_paint_estimate' => 'Estimasi Body & Paint',
        'system' => 'Sistem',
        'promo' => 'Promo',
        'info' => 'Info',
    ],

];
