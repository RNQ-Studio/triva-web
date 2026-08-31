<?php

namespace App\Services\PlayStore;

use App\Support\Enums\PlayStoreInstallsSource;
use App\Support\PlayStoreInstalls;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Angka total download dari ekspor laporan Play Console.
 *
 * Play Console menyalin laporan statistik ke bucket Cloud Storage milik akun
 * developer (`pubsite_prod_rev_<developer-id>`). Berkas ikhtisar bulanan
 * berada di `stats/installs/installs_<paket>_<YYYYMM>_overview.csv` dan berisi
 * kolom kumulatif "Total User Installs".
 *
 * Sumber ini diam-diam mengembalikan null selama bucket belum dikonfigurasi
 * atau belum bisa dibaca, sehingga pemanggilnya dapat kembali ke angka manual.
 */
final class PlayReportsInstallsSource implements InstallsSource
{
    private const TOTAL_COLUMN = 'total user installs';

    private const DATE_COLUMN = 'date';

    public function __construct(private readonly ?Filesystem $disk = null) {}

    public function fetch(): ?PlayStoreInstalls
    {
        $disk = $this->disk ?? $this->buildDisk();

        if ($disk === null) {
            return null;
        }

        $package = (string) config('play_store.package');
        $lookback = max(0, (int) config('play_store.installs.lookback_months', 3));
        $month = CarbonImmutable::now('UTC')->startOfMonth();

        for ($back = 0; $back <= $lookback; $back++) {
            $path = sprintf(
                'stats/installs/installs_%s_%s_overview.csv',
                $package,
                $month->subMonths($back)->format('Ym'),
            );

            try {
                if (! $disk->exists($path)) {
                    continue;
                }

                $raw = $disk->get($path);
            } catch (Throwable $exception) {
                // Bucket tidak terbaca sama sekali (kredensial, izin, jaringan):
                // mencoba bulan lain hanya mengulang kegagalan yang sama.
                Log::warning('Laporan instal Play Store tidak terbaca.', [
                    'path' => $path,
                    'message' => $exception->getMessage(),
                ]);

                return null;
            }

            $installs = is_string($raw) ? $this->parse($raw) : null;

            if ($installs !== null) {
                return $installs;
            }
        }

        return null;
    }

    private function buildDisk(): ?Filesystem
    {
        $bucket = config('play_store.installs.reports_bucket');
        $keyFile = config('filesystems.disks.gcs.key_file_path');

        if (blank($bucket) || blank($keyFile)) {
            return null;
        }

        try {
            return Storage::build([
                'driver' => 'gcs',
                'project_id' => config('filesystems.disks.gcs.project_id'),
                'key_file_path' => $keyFile,
                'bucket' => $bucket,
                // Laporan hanya dibaca; kegagalan ditangani pemanggil lewat null.
                'throw' => true,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Disk laporan Play Store gagal dibangun.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function parse(string $raw): ?PlayStoreInstalls
    {
        $rows = preg_split('/\r\n|\r|\n/', $this->toUtf8($raw)) ?: [];
        $rows = array_values(array_filter(
            array_map(trim(...), $rows),
            static fn (string $row): bool => $row !== '',
        ));

        if (count($rows) < 2) {
            return null;
        }

        $header = array_map(
            static fn (string $column): string => strtolower(trim($column)),
            str_getcsv((string) array_shift($rows), escape: '\\'),
        );

        $totalIndex = array_search(self::TOTAL_COLUMN, $header, true);

        if ($totalIndex === false) {
            return null;
        }

        $dateIndex = array_search(self::DATE_COLUMN, $header, true);

        $total = null;
        $reportedAt = null;

        // Baris laporan urut menaik menurut tanggal dan kolom totalnya
        // kumulatif, jadi baris terisi terakhir adalah angka terbaru.
        foreach ($rows as $row) {
            $columns = str_getcsv($row, escape: '\\');
            $value = trim((string) ($columns[$totalIndex] ?? ''));

            if ($value === '' || ! ctype_digit($value)) {
                continue;
            }

            $total = (int) $value;
            $reportedAt = $dateIndex === false
                ? null
                : $this->date($columns[$dateIndex] ?? null);
        }

        if ($total === null) {
            return null;
        }

        return new PlayStoreInstalls(
            totalInstalls: $total,
            source: PlayStoreInstallsSource::PlayReports,
            reportedAt: $reportedAt,
        );
    }

    private function date(?string $value): ?CarbonImmutable
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw)->setTimezone('UTC');
        } catch (Throwable) {
            return null;
        }
    }

    /** Berkas laporan Play Console dikirim sebagai UTF-16LE ber-BOM. */
    private function toUtf8(string $raw): string
    {
        foreach ([["\xFF\xFE", 'UTF-16LE'], ["\xFE\xFF", 'UTF-16BE']] as [$bom, $encoding]) {
            if (str_starts_with($raw, $bom)) {
                return (string) mb_convert_encoding(substr($raw, 2), 'UTF-8', $encoding);
            }
        }

        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
