<?php

namespace App\Services\PlayStore;

use App\Support\Enums\PlayStoreInstallsSource;
use App\Support\PlayStoreInstalls;
use Illuminate\Support\Facades\DB;

/**
 * Perangkat unik yang pernah membuka aplikasi.
 *
 * Dihitung dari `user_devices`, bukan dari `visit_events`: `visit_key` adalah
 * HMAC UUID acak per sesi (DEC-024) sehingga menghitungnya berarti menghitung
 * sesi, bukan perangkat. `device_id` adalah satu-satunya identitas perangkat
 * yang stabil di sistem ini.
 *
 * Seluruh rute aplikasi selain splash dan login mengalihkan pengunjung yang
 * belum masuk ke halaman login, jadi perangkat yang benar-benar memakai
 * aplikasi pasti terdaftar di sini. Yang tidak terhitung adalah perangkat yang
 * memasang lalu berhenti di layar login; karena itu angkanya lebih rendah
 * daripada jumlah unduhan Play Store yang sesungguhnya.
 *
 * Satu perangkat yang dipakai beberapa akun tetap dihitung sekali karena
 * `device_id`-nya sama, meski barisnya lebih dari satu.
 */
final class UniqueDevicesInstallsSource implements InstallsSource
{
    /** Selalu menghasilkan angka; nol perangkat tetap angka yang sah. */
    public function fetch(): PlayStoreInstalls
    {
        return new PlayStoreInstalls(
            totalInstalls: DB::table('user_devices')->distinct()->count('device_id'),
            source: PlayStoreInstallsSource::UniqueDevices,
            // Angkanya dihitung langsung dari basis data, jadi tanggal
            // berlakunya sama dengan waktu pengambilan di `generated_at`.
            reportedAt: null,
        );
    }
}
