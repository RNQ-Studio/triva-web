<?php

/**
 * Curated, year-aware starter catalog.
 *
 * This intentionally covers only variants that have a checked source. Models
 * without a reliable year-specific catalog continue to use the manual fallback
 * in API v1 instead of receiving guessed entries.
 */
return [
    'toyota' => [
        'Calya' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/calya',
            'variants' => [
                ['name' => '1.2 E MT STD', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 E MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 G MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 G AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Avanza' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/avanza',
            'variants' => [
                ['name' => '1.3 E M/T', 'year_from' => 2019, 'year_to' => 2021, 'transmission' => 'manual', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/sites/default/files/2021-03/brochures/Leaflet_Avanza_2021.pdf'],
                ['name' => '1.3 E A/T', 'year_from' => 2019, 'year_to' => 2021, 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/sites/default/files/2021-03/brochures/Leaflet_Avanza_2021.pdf'],
                ['name' => '1.3 G M/T', 'year_from' => 2019, 'year_to' => 2021, 'transmission' => 'manual', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/sites/default/files/2021-03/brochures/Leaflet_Avanza_2021.pdf'],
                ['name' => '1.3 G A/T', 'year_from' => 2019, 'year_to' => 2021, 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/sites/default/files/2021-03/brochures/Leaflet_Avanza_2021.pdf'],
                ['name' => '1.3 E M/T', 'year_from' => 2021, 'year_to' => 2025, 'transmission' => 'manual', 'fuel_type' => 'gasoline', 'source_url' => 'https://pressroom.toyota.astra.co.id/50-tahun-toyota-di-indonesia-world-premiere-all-new-avanza-menjadi-bagian-dari-kebesaran-indonesia'],
                ['name' => '1.5 G M/T', 'year_from' => 2021, 'year_to' => 2025, 'transmission' => 'manual', 'fuel_type' => 'gasoline', 'source_url' => 'https://pressroom.toyota.astra.co.id/50-tahun-toyota-di-indonesia-world-premiere-all-new-avanza-menjadi-bagian-dari-kebesaran-indonesia'],
                ['name' => '1.5 G CVT', 'year_from' => 2021, 'year_to' => 2025, 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://pressroom.toyota.astra.co.id/50-tahun-toyota-di-indonesia-world-premiere-all-new-avanza-menjadi-bagian-dari-kebesaran-indonesia'],
                ['name' => '1.5 G CVT TSS', 'year_from' => 2021, 'year_to' => 2025, 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://pressroom.toyota.astra.co.id/50-tahun-toyota-di-indonesia-world-premiere-all-new-avanza-menjadi-bagian-dari-kebesaran-indonesia'],
                ['name' => '1.3 E M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/shopping-tools/financial-simulation?model=AVANZA'],
                ['name' => '1.3 E CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/shopping-tools/financial-simulation?model=AVANZA'],
                ['name' => '1.5 G M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/shopping-tools/financial-simulation?model=AVANZA'],
                ['name' => '1.5 G CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/shopping-tools/financial-simulation?model=AVANZA'],
            ],
        ],
        'Rush' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/rush',
            'variants' => [
                ['name' => 'G MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'G AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 S MT GR Sport', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 S AT GR Sport', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Agya' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/agya',
            'variants' => [
                ['name' => '1.2L E M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L G M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L G CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L G CVT Stylix With GR Parts Aero Package', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L GR Sport M/T Non Premium Spec', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L GR Sport M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L GR Sport CVT Non Premium Spec', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L GR Sport CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Kijang Innova' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/kijang-innova',
            'variants' => [
                ['name' => '2.4 G M/T Diesel', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'diesel'],
                ['name' => '2.4 G A/T Diesel', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
            ],
        ],
        'Raize' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/raize',
            'variants' => [
                ['name' => '1.2 G MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 G CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 Turbo G MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 Turbo G CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 Turbo GR Sport CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 Turbo GR Sport CVT Two Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 Turbo GR Sport CVT TSS Two Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Fortuner' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/fortuner',
            'variants' => [
                ['name' => '2.4 G MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'diesel'],
                ['name' => '2.4 G AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.8 VRZ 4x2 AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.8 VRZ 4x2 AT TSS', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.8 VRZ 4x2 AT TSS GR Aeropackage', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.8 VRZ 4x4 AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.8 VRZ 4x4 AT GR Sport', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.7 SRZ', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '2.7 SRZ GR Aeropackage', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Veloz' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/veloz',
            'variants' => [
                ['name' => 'MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'Q CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'Q CVT TSS', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Yaris' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/yaris',
            'variants' => [
                ['name' => '1.5 S CVT GR Sport 3 AB', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 S CVT GR Sport 7 AB', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Kijang Innova Zenix' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/kijang-innova-zenix',
            'variants' => [
                ['name' => '2.0L G CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '2.0L V CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Yaris Cross' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/yaris-cross',
            'variants' => [
                ['name' => '1.5 G M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 G CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 G Hybrid EV CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => '1.5 S CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 S CVT With GR Parts Aero Package', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 S Hybrid EV CVT TSS', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => '1.5 S GR Hybrid EV CVT TSS', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
    ],
];
