<?php

/*
|--------------------------------------------------------------------------
| Rate card simulasi kredit ACC (revisi 4 September 2026)
|--------------------------------------------------------------------------
|
| Diturunkan dari lembar kerja "Simulasi_ACC" cabang. Bunga flat per tahun
| dibaca dari matriks kelas kendaraan x tingkat DP x tenor; premi asuransi
| kredit (all risk tahun pertama + TLO tahun berikutnya) dibaca dari matriks
| batas OTR x tenor. Semua angka dalam basis poin (1% = 100 bp) supaya
| perhitungan tetap bilangan bulat.
|
| Lembar kerja hanya memuat kolom DP 25% dan 30%. Untuk pilihan DP 20% dipakai
| kolom 25% (asumsi yang wajib dikonfirmasi cabang; ubah di sini bila rate card
| resminya berbeda).
*/

return [
    'formula_version' => 'acc-flat-v1',

    'dp_percent_options' => [20, 25, 30],

    'tenor_years_options' => [1, 2, 3, 4, 5],

    /*
     | Biaya tetap yang dibayar di muka bersama DP dan angsuran pertama.
     */
    'administration_fee' => (int) env('CREDIT_ACC_ADMIN_FEE', 1_000_000),
    'liability_insurance_fee' => (int) env('CREDIT_ACC_TJH_FEE', 400_000),

    /*
     | Pembulatan mengikuti lembar kerja: DP, premi asuransi, dan angsuran
     | dibulatkan ke ribuan terdekat.
     */
    'rounding' => 1_000,

    'default_vehicle_class' => 'reg2',

    /*
     | Pemetaan model kendaraan ke kelas rate. Pencocokan memakai kata kunci
     | yang terkandung dalam nama model (huruf kecil).
     */
    'vehicle_classes' => [
        'agya' => ['agya'],
        'reg1' => ['calya', 'raize', 'rush', 'yaris', 'avanza', 'vios', 'sienta'],
        'reg2' => ['veloz', 'innova', 'zenix', 'reborn', 'fortuner', 'corolla', 'camry', 'hilux', 'voxy', 'hiace'],
        'lux' => ['alphard', 'vellfire', 'land cruiser', 'crown', 'bz4x', 'gr '],
    ],

    /*
     | Bunga flat per tahun (basis poin) per tenor 1..5 tahun.
     | Kelas AGYA hanya punya satu kolom pada lembar kerja.
     */
    'interest_rates' => [
        'agya' => [
            '25' => [1 => 585, 2 => 668, 3 => 744, 4 => 820, 5 => 1005],
            '30' => [1 => 585, 2 => 668, 3 => 744, 4 => 820, 5 => 1005],
        ],
        'reg1' => [
            '25' => [1 => 595, 2 => 695, 3 => 745, 4 => 895, 5 => 945],
            '30' => [1 => 595, 2 => 670, 3 => 710, 4 => 825, 5 => 878],
        ],
        'reg2' => [
            '25' => [1 => 620, 2 => 720, 3 => 770, 4 => 920, 5 => 970],
            '30' => [1 => 620, 2 => 695, 3 => 735, 4 => 850, 5 => 900],
        ],
        'lux' => [
            '25' => [1 => 570, 2 => 670, 3 => 720, 4 => 870, 5 => 920],
            '30' => [1 => 570, 2 => 645, 3 => 685, 4 => 800, 5 => 850],
        ],
    ],

    /*
     | Premi asuransi kredit kumulatif (basis poin dari OTR) untuk paket all
     | risk tahun pertama + TLO tahun berikutnya, per batas atas OTR dan tenor.
     | Baris terakhir (max = null) berlaku untuk OTR di atas batas sebelumnya.
     */
    'insurance_rates' => [
        ['max' => 125_000_000, 'rates' => [1 => 315, 2 => 389, 3 => 462, 4 => 527, 5 => 591]],
        ['max' => 156_250_000, 'rates' => [1 => 278, 2 => 352, 3 => 425, 4 => 490, 5 => 554]],
        ['max' => 178_571_429, 'rates' => [1 => 278, 2 => 347, 3 => 416, 4 => 480, 5 => 544]],
        ['max' => 200_000_000, 'rates' => [1 => 278, 2 => 347, 3 => 416, 4 => 476, 5 => 536]],
        ['max' => 250_000_000, 'rates' => [1 => 234, 2 => 303, 3 => 372, 4 => 432, 5 => 492]],
        ['max' => 285_714_286, 'rates' => [1 => 234, 2 => 301, 3 => 368, 4 => 429, 5 => 489]],
        ['max' => 400_000_000, 'rates' => [1 => 234, 2 => 301, 3 => 368, 4 => 427, 5 => 486]],
        ['max' => 500_000_000, 'rates' => [1 => 212, 2 => 279, 3 => 346, 4 => 405, 5 => 464]],
        ['max' => 571_428_571, 'rates' => [1 => 212, 2 => 275, 3 => 338, 4 => 397, 5 => 456]],
        ['max' => 800_000_000, 'rates' => [1 => 212, 2 => 275, 3 => 338, 4 => 394, 5 => 449]],
        ['max' => 1_000_000_000, 'rates' => [1 => 185, 2 => 248, 3 => 311, 4 => 367, 5 => 422]],
        ['max' => 1_142_857_143, 'rates' => [1 => 185, 2 => 243, 3 => 302, 4 => 357, 5 => 412]],
        ['max' => null, 'rates' => [1 => 185, 2 => 243, 3 => 302, 4 => 353, 5 => 404]],
    ],

    /*
     | Unit rekomendasi pada hasil appraisal, berdasarkan rentang harga
     | appraisal (revisi 4 September 2026). Kunci mengacu ke
     | credit_programs.unit_key; Veloz Hybrid selalu ditawarkan.
     */
    'appraisal_recommendations' => [
        ['min' => 150_000_000, 'units' => ['veloz_hybrid', 'zenix_hybrid']],
        ['min' => 100_000_000, 'units' => ['veloz_hybrid', 'innova_reborn']],
        ['min' => 0, 'units' => ['raize', 'veloz_hybrid']],
    ],
];
