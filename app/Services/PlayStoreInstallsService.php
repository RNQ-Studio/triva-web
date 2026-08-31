<?php

namespace App\Services;

use App\Services\PlayStore\InstallsSource;
use App\Services\PlayStore\ManualInstallsSource;
use App\Services\PlayStore\PlayReportsInstallsSource;
use App\Support\Enums\PlayStoreInstallsSource;
use App\Support\PlayStoreInstalls;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Total download aplikasi di Google Play untuk panel admin.
 *
 * Sumber yang dipilih lewat `play_store.installs.source` dicoba lebih dulu;
 * kalau belum menghasilkan angka, angka manual dari App Config dipakai supaya
 * panel admin tetap terisi selama ekspor laporan Play Console belum aktif.
 */
class PlayStoreInstallsService
{
    public const CACHE_KEY = 'play_store:installs';

    public function __construct(
        private readonly ManualInstallsSource $manual,
        private readonly PlayReportsInstallsSource $playReports,
    ) {}

    /** @return array<string, mixed> */
    public function summarize(): array
    {
        $installs = $this->resolveCached();

        return [
            'package_name' => (string) config('play_store.package'),
            'configured' => $installs !== null,
            'total_installs' => $installs?->totalInstalls,
            'source' => $installs?->source->value,
            'reported_at' => $installs?->reportedAt?->toIso8601String(),
            'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
    }

    public static function bustCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function resolveCached(): ?PlayStoreInstalls
    {
        $ttl = max(0, (int) config('play_store.installs.cache_ttl', 3600));

        if ($ttl === 0) {
            return $this->resolve();
        }

        // Hasil disimpan sebagai array supaya cache tetap terbaca kalau bentuk
        // objeknya berubah pada rilis berikutnya.
        return $this->hydrate(Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds($ttl),
            fn (): array => $this->resolve()?->toArray() ?? [],
        ));
    }

    private function resolve(): ?PlayStoreInstalls
    {
        foreach ($this->sources() as $source) {
            $installs = $source->fetch();

            if ($installs !== null) {
                return $installs;
            }
        }

        return null;
    }

    /** @return array<int, InstallsSource> */
    private function sources(): array
    {
        $configured = PlayStoreInstallsSource::tryFrom(
            (string) config('play_store.installs.source', 'manual'),
        ) ?? PlayStoreInstallsSource::Manual;

        return $configured === PlayStoreInstallsSource::PlayReports
            ? [$this->playReports, $this->manual]
            : [$this->manual];
    }

    /** @param  array<string, mixed>  $cached */
    private function hydrate(array $cached): ?PlayStoreInstalls
    {
        $total = $cached['total_installs'] ?? null;
        $source = PlayStoreInstallsSource::tryFrom((string) ($cached['source'] ?? ''));

        if (! is_int($total) || $source === null) {
            return null;
        }

        $reportedAt = $cached['reported_at'] ?? null;

        return new PlayStoreInstalls(
            totalInstalls: $total,
            source: $source,
            reportedAt: is_string($reportedAt)
                ? CarbonImmutable::parse($reportedAt)->setTimezone('UTC')
                : null,
        );
    }
}
