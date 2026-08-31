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
    | Karena itu ada tiga sumber:
    |
    |   unique_devices perangkat unik yang pernah membuka aplikasi, dihitung
    |                  dari `user_devices`. Ini yang dipakai sekarang.
    |   manual         angka disalin admin dari Play Console lewat App Config.
    |   play_reports   berkas CSV laporan instal dibaca langsung dari bucket.
    |
    | `unique_devices` bukan jumlah unduhan Play Store: perangkat yang memasang
    | lalu berhenti di layar login tidak terhitung, sehingga angkanya lebih
    | rendah daripada unduhan sesungguhnya. Ditukar dengan `play_reports` kalau
    | angka Play Store yang sebenarnya dibutuhkan.
    |
    | `play_reports` baru bisa dipakai setelah nama bucket diisi dan service
    | account Play diberi akses baca ke bucket tersebut. Selama sumber utama
    | belum menghasilkan angka, angka manual dipakai supaya panel tidak kosong.
    |
    */

    'installs' => [
        'source' => env('PLAY_STORE_INSTALLS_SOURCE', 'unique_devices'),

        'reports_bucket' => env('PLAY_STORE_REPORTS_BUCKET'),

        // Cukup pendek supaya perangkat yang baru masuk terlihat tanpa lama
        // menunggu, cukup panjang supaya bucket laporan tidak ditembak pada
        // setiap kali panel admin dibuka.
        'cache_ttl' => (int) env('PLAY_STORE_INSTALLS_CACHE_TTL', 300),

        // Laporan bulan berjalan kadang belum terbit di awal bulan, jadi
        // berkas bulan-bulan sebelumnya ikut dicoba.
        'lookback_months' => (int) env('PLAY_STORE_INSTALLS_LOOKBACK_MONTHS', 3),
    ],
];
