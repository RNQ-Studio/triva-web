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
    'honda' => [
        'Brio' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/brio',
            'variants' => [
                ['name' => 'Satya S M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Satya S CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'Satya E M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Satya E CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'RS M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'RS CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'WR-V' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/wr-v',
            'variants' => [
                ['name' => '1.5L E CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5L RS CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5L RS With Honda Sensing CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'BRV' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/br-v',
            'variants' => [
                ['name' => 'S MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'E MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'N7X E CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'N7X Prestige CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'N7X Prestige With Honda Sensing CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'HRV' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/hr-v',
            'variants' => [
                ['name' => 'E CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'E Plus CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'e:HEV', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'e:HEV Modulo', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'RS e:HEV', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'CR-V' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/cr-v',
            'variants' => [
                ['name' => '2.0L e:HEV', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => '2.0L RS e:HEV', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'City Hatchback' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/city-hatchback',
            'variants' => [
                ['name' => 'RS CVT Honda Sensing', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Civic RS' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/civic-rs',
            'variants' => [
                ['name' => 'e:HEV', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'Civic Type R' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/civic-type-r',
            'variants' => [
                ['name' => '6-Speed MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
            ],
        ],
        'City' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/city',
            'variants' => [
                ['name' => 'E CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'StepWGN e:HEV' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/stepwgn-ehev',
            'variants' => [
                ['name' => '2.0L e:HEV', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'e:N1' => [
            'source_url' => 'https://www.honda-indonesia.com/brochures/39/download',
            'variants' => [
                ['name' => 'EV', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'electric'],
            ],
        ],
        'Accord' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/accord',
            'variants' => [
                ['name' => '2.0L RS e:HEV', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'Prelude' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/prelude',
            'variants' => [
                ['name' => 'e:HEV', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
    ],
    'daihatsu' => [
        'Sigra' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/sigra',
            'variants' => [
                ['name' => '1.0 D MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 M MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X DLX MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 R MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 R DLX MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X DLX AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 R AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 R DLX AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Ayla' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/ayla',
            'variants' => [
                ['name' => '1.0L M MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0L X MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0L X MT ADS', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L R MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0L X CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L R MT ADS', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0L X CVT ADS', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L R CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L R CVT ADS', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Xenia' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/xenia',
            'variants' => [
                ['name' => '1.3 M MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 X MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 R MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 X CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 R MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 R CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 R CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 R CVT ASA', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Terios' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/terios',
            'variants' => [
                ['name' => 'X M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'X A/T', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'X ADS MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'X ADS AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'R M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'R ADS MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'R A/T', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'R ADS AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'R MT Custom', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'R AT Custom', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Rocky' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/rocky',
            'variants' => [
                ['name' => '1.2 M MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 M CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X MT ADS', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X CVT ADS', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R ADS MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R ADS CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R ASA CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R TC CVT ASA Plus', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Gran Max PU' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/gran-max-pu',
            'variants' => [
                ['name' => '1.3 STD FH E4', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 STD MC', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 STD AC&PS', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Gran Max MB' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/gran-max-mb',
            'variants' => [
                ['name' => 'Blind Van 1.3 STD', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Blind Van 1.3 AC', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 D FH', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 D FF FH', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Blind Van 1.5 AT AC PS ABS', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 D PS FH', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Luxio' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/luxio',
            'variants' => [
                ['name' => '1.5 D M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 X M/T', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 X A/T', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Sirion' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/sirion',
            'variants' => [
                ['name' => 'X CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'R CVT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Rocky Hybrid' => [
            'source_url' => 'https://daihatsu.co.id/giias/produk/rocky-hybrid',
            'variants' => [
                ['name' => '1.2L e-SMART Hybrid', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
    ],
    'suzuki' => [
        'Grand Vitara' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'GX MC', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'GX MC Two Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'e-Vitara' => [
            'source_url' => 'https://www.suzuki.co.id/automobile/evitara',
            'variants' => [
                ['name' => 'GLX Single Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'electric'],
                ['name' => 'GLX Two Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'electric'],
            ],
        ],
        'XL7' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'ZETA MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'ZETA AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'Hybrid BETA MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid BETA AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA MT Two Tone', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA AT Two Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA AT Kuro', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA AT Kuro Two Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'Ertiga' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'GA MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GL MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GL AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Ertiga Smart Hybrid' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'GX MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'GX AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Cruise MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'Cruise MT Two Tone', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'Cruise AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Cruise AT Two Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'Jimny' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => '3-Door AT Single Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '3-Door AT Two Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Jimny 5 Door' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'MT Single Tone', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'AT Single Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'MT Two Tone', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'AT Two Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Fronx' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'GL MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GL AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'GX MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'GX AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'SGX AT', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'SGX AT Two Tone', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'APV Arena' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'Blind Van', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GE', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GL', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GX', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'SGX', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Carry' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'Flat-Deck STD', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Flat-Deck AC-PS', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Wide-Deck STD', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Wide-Deck AC-PS', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
            ],
        ],
        'S-Presso' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'MT', 'year_from' => 2026, 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Auto Gear Shift', 'year_from' => 2026, 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
    ],
];
