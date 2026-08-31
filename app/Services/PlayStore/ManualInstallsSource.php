<?php

namespace App\Services\PlayStore;

use App\Models\AppConfig;
use App\Support\Enums\PlayStoreInstallsSource;
use App\Support\PlayStoreInstalls;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Angka total download yang disalin admin dari Play Console.
 *
 * Kedua kunci sengaja bertipe `string`, bukan `integer`: App Config mengecor
 * nilai integer yang kosong menjadi 0, sehingga "belum pernah diisi" tidak lagi
 * bisa dibedakan dari "benar-benar nol pemasangan". Panel admin perlu
 * membedakan keduanya supaya bisa menampilkan ajakan mengisi.
 */
final class ManualInstallsSource implements InstallsSource
{
    public const TOTAL_KEY = 'play_store_total_installs';

    public const REPORTED_AT_KEY = 'play_store_installs_reported_at';

    public function fetch(): ?PlayStoreInstalls
    {
        $total = $this->digits(AppConfig::get(self::TOTAL_KEY));

        if ($total === null) {
            return null;
        }

        return new PlayStoreInstalls(
            totalInstalls: $total,
            source: PlayStoreInstallsSource::Manual,
            reportedAt: $this->date(AppConfig::get(self::REPORTED_AT_KEY)),
        );
    }

    private function digits(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $raw = trim((string) $value);

        // Nilai yang tidak murni angka diperlakukan sebagai belum diisi;
        // menampilkan hasil `(int)` dari teks salah ketik lebih menyesatkan
        // daripada menampilkan keadaan kosong.
        return $raw !== '' && ctype_digit($raw) ? (int) $raw : null;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_scalar($value)) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw)->setTimezone('UTC');
        } catch (Throwable) {
            // Tanggal salah ketik tidak boleh menjatuhkan seluruh statistik;
            // angkanya tetap berguna tanpa keterangan tanggal.
            return null;
        }
    }
}
