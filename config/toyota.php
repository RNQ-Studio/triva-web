<?php

return [
    /*
     * Masa cakupan T-Care sejak tahun kendaraan. Notulensi 19 Agustus 2026
     * meminta hitung mundur ini supaya pelanggan yang masih tercakup diarahkan
     * servis ke Auto2000, bukan ke OtoXpert. Dibuat konfigurasi karena
     * ketentuan programnya bisa berubah mengikuti kebijakan TAM.
     */
    't_care' => [
        'coverage_years' => (int) env('TOYOTA_T_CARE_COVERAGE_YEARS', 4),
    ],
];
