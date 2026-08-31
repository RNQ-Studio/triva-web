<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paket aplikasi di Google Play
    |--------------------------------------------------------------------------
    |
    | Dipakai untuk menyusun nama berkas laporan instal Play Console dan
    | ditampilkan apa adanya pada panel admin.
    |
    */

    'package' => env('PLAY_STORE_PACKAGE', 'id.rnq.triva'),

    /*
    |--------------------------------------------------------------------------
    | Sumber angka total download
    |--------------------------------------------------------------------------
    |
    | Tidak ada API Google Play yang mengembalikan jumlah instal: Play Developer
    | Reporting API hanya menyediakan metrik vitals (crash, ANR, memori,
    | rendering) dan Android Publisher API hanya mengurus rilis. Satu-satunya
    | sumber terprogram adalah ekspor laporan Play Console di bucket
    | `pubsite_prod_rev_<developer-id>`.
    |
    | Karena itu ada dua sumber:
    |
    |   manual       angka disalin admin dari Play Console lewat App Config.
    |   play_reports berkas CSV laporan instal dibaca langsung dari bucket.
    |
    | `play_reports` baru bisa dipakai setelah nama bucket diisi dan service
    | account Play diberi akses baca ke bucket tersebut. Selama laporan belum
    | terbaca, angka manual tetap dipakai supaya panel admin tidak kosong.
    |
    */

    'installs' => [
        'source' => env('PLAY_STORE_INSTALLS_SOURCE', 'manual'),

        'reports_bucket' => env('PLAY_STORE_REPORTS_BUCKET'),

        // Laporan Play Console hanya diperbarui harian, jadi tidak ada gunanya
        // menembak bucket pada setiap kali panel admin dibuka.
        'cache_ttl' => (int) env('PLAY_STORE_INSTALLS_CACHE_TTL', 3600),

        // Laporan bulan berjalan kadang belum terbit di awal bulan, jadi
        // berkas bulan-bulan sebelumnya ikut dicoba.
        'lookback_months' => (int) env('PLAY_STORE_INSTALLS_LOOKBACK_MONTHS', 3),
    ],
];
