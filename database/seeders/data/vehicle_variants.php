<?php

/**
 * Curated, model-scoped starter catalog.
 *
 * This intentionally covers only variants that have a checked source. Models
 * without a reliable catalog continue to use the manual fallback in API v1
 * instead of receiving guessed entries.
 */
return [
    'toyota' => [
        'Calya' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/calya',
            'variants' => [
                ['name' => '1.2 E MT STD', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 E MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 G MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 G AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Avanza' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/avanza',
            'variants' => [
                ['name' => '1.3 E A/T', 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/sites/default/files/2021-03/brochures/Leaflet_Avanza_2021.pdf'],
                ['name' => '1.3 G M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/sites/default/files/2021-03/brochures/Leaflet_Avanza_2021.pdf'],
                ['name' => '1.3 G A/T', 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/sites/default/files/2021-03/brochures/Leaflet_Avanza_2021.pdf'],
                ['name' => '1.5 G CVT TSS', 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://pressroom.toyota.astra.co.id/50-tahun-toyota-di-indonesia-world-premiere-all-new-avanza-menjadi-bagian-dari-kebesaran-indonesia'],
                ['name' => '1.3 E M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/shopping-tools/financial-simulation?model=AVANZA'],
                ['name' => '1.3 E CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/shopping-tools/financial-simulation?model=AVANZA'],
                ['name' => '1.5 G M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/shopping-tools/financial-simulation?model=AVANZA'],
                ['name' => '1.5 G CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline', 'source_url' => 'https://www.toyota.astra.co.id/shopping-tools/financial-simulation?model=AVANZA'],
            ],
        ],
        'Rush' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/rush',
            'variants' => [
                ['name' => 'G MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'G AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 S MT GR Sport', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 S AT GR Sport', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Agya' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/agya',
            'variants' => [
                ['name' => '1.2L E M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L G M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L G CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L G CVT Stylix With GR Parts Aero Package', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L GR Sport M/T Non Premium Spec', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L GR Sport M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L GR Sport CVT Non Premium Spec', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L GR Sport CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Kijang Innova' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/kijang-innova',
            'variants' => [
                ['name' => '2.4 G M/T Diesel', 'transmission' => 'manual', 'fuel_type' => 'diesel'],
                ['name' => '2.4 G A/T Diesel', 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
            ],
        ],
        'Raize' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/raize',
            'variants' => [
                ['name' => '1.2 G MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 G CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 Turbo G MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 Turbo G CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 Turbo GR Sport CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 Turbo GR Sport CVT Two Tone', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 Turbo GR Sport CVT TSS Two Tone', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Fortuner' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/fortuner',
            'variants' => [
                ['name' => '2.4 G MT', 'transmission' => 'manual', 'fuel_type' => 'diesel'],
                ['name' => '2.4 G AT', 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.8 VRZ 4x2 AT', 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.8 VRZ 4x2 AT TSS', 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.8 VRZ 4x2 AT TSS GR Aeropackage', 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.8 VRZ 4x4 AT', 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.8 VRZ 4x4 AT GR Sport', 'transmission' => 'automatic', 'fuel_type' => 'diesel'],
                ['name' => '2.7 SRZ', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '2.7 SRZ GR Aeropackage', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Veloz' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/veloz',
            'variants' => [
                ['name' => 'MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'Q CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'Q CVT TSS', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Yaris' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/yaris',
            'variants' => [
                ['name' => '1.5 S CVT GR Sport 3 AB', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 S CVT GR Sport 7 AB', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Kijang Innova Zenix' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/kijang-innova-zenix',
            'variants' => [
                ['name' => '2.0L G CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '2.0L V CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Yaris Cross' => [
            'source_url' => 'https://www.oto.com/mobil-baru/toyota/yaris-cross',
            'variants' => [
                ['name' => '1.5 G M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 G CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 G Hybrid EV CVT', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => '1.5 S CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 S CVT With GR Parts Aero Package', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 S Hybrid EV CVT TSS', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => '1.5 S GR Hybrid EV CVT TSS', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
    ],
    'honda' => [
        'Brio' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/brio',
            'variants' => [
                ['name' => 'Satya S M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Satya S CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'Satya E M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Satya E CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'RS M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'RS CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'WR-V' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/wr-v',
            'variants' => [
                ['name' => '1.5L E CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5L RS CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5L RS With Honda Sensing CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'BRV' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/br-v',
            'variants' => [
                ['name' => 'S MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'E MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'N7X E CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'N7X Prestige CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'N7X Prestige With Honda Sensing CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'HRV' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/hr-v',
            'variants' => [
                ['name' => 'E CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'E Plus CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'e:HEV', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'e:HEV Modulo', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'RS e:HEV', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'CR-V' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/cr-v',
            'variants' => [
                ['name' => '2.0L e:HEV', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => '2.0L RS e:HEV', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'City Hatchback' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/city-hatchback',
            'variants' => [
                ['name' => 'RS CVT Honda Sensing', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Civic RS' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/civic-rs',
            'variants' => [
                ['name' => 'e:HEV', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'Civic Type R' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/civic-type-r',
            'variants' => [
                ['name' => '6-Speed MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
            ],
        ],
        'City' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/city',
            'variants' => [
                ['name' => 'E CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'StepWGN e:HEV' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/stepwgn-ehev',
            'variants' => [
                ['name' => '2.0L e:HEV', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'e:N1' => [
            'source_url' => 'https://www.honda-indonesia.com/brochures/39/download',
            'variants' => [
                ['name' => 'EV', 'transmission' => 'automatic', 'fuel_type' => 'electric'],
            ],
        ],
        'Accord' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/accord',
            'variants' => [
                ['name' => '2.0L RS e:HEV', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'Prelude' => [
            'source_url' => 'https://www.oto.com/mobil-baru/honda/prelude',
            'variants' => [
                ['name' => 'e:HEV', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
    ],
    'daihatsu' => [
        'Sigra' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/sigra',
            'variants' => [
                ['name' => '1.0 D MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 M MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X DLX MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 R MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 R DLX MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X DLX AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 R AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 R DLX AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Ayla' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/ayla',
            'variants' => [
                ['name' => '1.0L M MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0L X MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0L X MT ADS', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L R MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0L X CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L R MT ADS', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0L X CVT ADS', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L R CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2L R CVT ADS', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Xenia' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/xenia',
            'variants' => [
                ['name' => '1.3 M MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 X MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 R MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 X CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 R MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 R CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 R CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 R CVT ASA', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Terios' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/terios',
            'variants' => [
                ['name' => 'X M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'X A/T', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'X ADS MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'X ADS AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'R M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'R ADS MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'R A/T', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'R ADS AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'R MT Custom', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'R AT Custom', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Rocky' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/rocky',
            'variants' => [
                ['name' => '1.2 M MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 M CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X MT ADS', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.2 X CVT ADS', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R ADS MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R ADS CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R ASA CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.0 R TC CVT ASA Plus', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Gran Max PU' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/gran-max-pu',
            'variants' => [
                ['name' => '1.3 STD FH E4', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 STD MC', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 STD AC&PS', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Gran Max MB' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/gran-max-mb',
            'variants' => [
                ['name' => 'Blind Van 1.3 STD', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Blind Van 1.3 AC', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 D FH', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.3 D FF FH', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Blind Van 1.5 AT AC PS ABS', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 D PS FH', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Luxio' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/luxio',
            'variants' => [
                ['name' => '1.5 D M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 X M/T', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => '1.5 X A/T', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Sirion' => [
            'source_url' => 'https://www.oto.com/mobil-baru/daihatsu/sirion',
            'variants' => [
                ['name' => 'X CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'R CVT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Rocky Hybrid' => [
            'source_url' => 'https://daihatsu.co.id/giias/produk/rocky-hybrid',
            'variants' => [
                ['name' => '1.2L e-SMART Hybrid', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
    ],
    'suzuki' => [
        'Grand Vitara' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'GX MC', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'GX MC Two Tone', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'e-Vitara' => [
            'source_url' => 'https://www.suzuki.co.id/automobile/evitara',
            'variants' => [
                ['name' => 'GLX Single Tone', 'transmission' => 'automatic', 'fuel_type' => 'electric'],
                ['name' => 'GLX Two Tone', 'transmission' => 'automatic', 'fuel_type' => 'electric'],
            ],
        ],
        'XL7' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'ZETA MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'ZETA AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'Hybrid BETA MT', 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid BETA AT', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA MT', 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA AT', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA MT Two Tone', 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA AT Two Tone', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA AT Kuro', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Hybrid ALPHA AT Kuro Two Tone', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'Ertiga' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'GA MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GL MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GL AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Ertiga Smart Hybrid' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'GX MT', 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'GX AT', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Cruise MT', 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'Cruise MT Two Tone', 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'Cruise AT', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'Cruise AT Two Tone', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'Jimny' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => '3-Door AT Single Tone', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => '3-Door AT Two Tone', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Jimny 5 Door' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'MT Single Tone', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'AT Single Tone', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'MT Two Tone', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'AT Two Tone', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Fronx' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'GL MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GL AT', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
                ['name' => 'GX MT', 'transmission' => 'manual', 'fuel_type' => 'hybrid'],
                ['name' => 'GX AT', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'SGX AT', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
                ['name' => 'SGX AT Two Tone', 'transmission' => 'automatic', 'fuel_type' => 'hybrid'],
            ],
        ],
        'APV Arena' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'Blind Van', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GE', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GL', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'GX', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'SGX', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
            ],
        ],
        'Carry' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'Flat-Deck STD', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Flat-Deck AC-PS', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Wide-Deck STD', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Wide-Deck AC-PS', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
            ],
        ],
        'S-Presso' => [
            'source_url' => 'https://www.suzuki.co.id/pricelist',
            'variants' => [
                ['name' => 'MT', 'transmission' => 'manual', 'fuel_type' => 'gasoline'],
                ['name' => 'Auto Gear Shift', 'transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            ],
        ],
    ],
];
