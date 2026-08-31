<?php

namespace App\Support;

use App\Support\Enums\PlayStoreInstallsSource;
use Carbon\CarbonImmutable;

/**
 * Jumlah pemasangan kumulatif aplikasi di Google Play.
 *
 * `totalInstalls` mengikuti kolom "Total User Installs" pada laporan Play
 * Console: jumlah pengguna unik yang pernah memasang, bukan perangkat yang
 * masih memasang sekarang.
 */
final readonly class PlayStoreInstalls
{
    public function __construct(
        public int $totalInstalls,
        public PlayStoreInstallsSource $source,
        /** Tanggal angka tersebut berlaku, bukan waktu pengambilan. */
        public ?CarbonImmutable $reportedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'total_installs' => $this->totalInstalls,
            'source' => $this->source->value,
            'reported_at' => $this->reportedAt?->toIso8601String(),
        ];
    }
}
