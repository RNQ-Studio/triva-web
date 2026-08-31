<?php

namespace App\Support\Enums;

enum PlayStoreInstallsSource: string
{
    /** Angka disalin admin dari Play Console lewat App Config. */
    case Manual = 'manual';

    /** Angka dibaca dari ekspor laporan instal Play Console di bucket GCS. */
    case PlayReports = 'play_reports';

    /**
     * Perangkat unik yang pernah membuka aplikasi, dihitung dari device yang
     * terdaftar. Bukan jumlah unduhan Play Store: perangkat yang memasang lalu
     * tidak pernah masuk tidak terhitung.
     */
    case UniqueDevices = 'unique_devices';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
