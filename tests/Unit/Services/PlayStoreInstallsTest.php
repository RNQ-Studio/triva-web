<?php

namespace Tests\Unit\Services;

use App\Models\AppConfig;
use App\Services\PlayStore\ManualInstallsSource;
use App\Services\PlayStore\PlayReportsInstallsSource;
use App\Services\PlayStoreInstallsService;
use App\Support\Enums\AppConfigType;
use App\Support\Enums\PlayStoreInstallsSource;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayStoreInstallsTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'Date,Package Name,Daily Device Installs,Daily Device Uninstalls,'
        .'Daily Device Upgrades,Total User Installs,Daily User Installs,'
        .'Daily User Uninstalls,Active Device Installs';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('play_store.package', 'id.rnq.triva');
        // Cache dimatikan supaya setiap skenario membaca sumbernya langsung.
        config()->set('play_store.installs.cache_ttl', 0);
        PlayStoreInstallsService::bustCache();
    }

    public function test_report_reader_takes_the_last_cumulative_row(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-31T00:00:00+00:00'));

        $disk = $this->diskWithReport('202608', [
            '2026-08-29,id.rnq.triva,10,1,0,12470,9,1,8100',
            '2026-08-30,id.rnq.triva,12,0,0,12482,12,0,8112',
        ]);

        $installs = (new PlayReportsInstallsSource($disk))->fetch();

        $this->assertNotNull($installs);
        $this->assertSame(12482, $installs->totalInstalls);
        $this->assertSame(PlayStoreInstallsSource::PlayReports, $installs->source);
        $this->assertSame(
            '2026-08-30T00:00:00+00:00',
            $installs->reportedAt?->toIso8601String(),
        );
    }

    public function test_report_reader_falls_back_to_an_earlier_month(): void
    {
        // Awal bulan: berkas Agustus belum terbit di bucket.
        $this->travelTo(CarbonImmutable::parse('2026-08-01T02:00:00+00:00'));

        $disk = $this->diskWithReport('202607', [
            '2026-07-31,id.rnq.triva,5,0,0,12000,5,0,7900',
        ]);

        $installs = (new PlayReportsInstallsSource($disk))->fetch();

        $this->assertNotNull($installs);
        $this->assertSame(12000, $installs->totalInstalls);
    }

    public function test_report_reader_stops_looking_beyond_the_lookback_window(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-31T00:00:00+00:00'));
        config()->set('play_store.installs.lookback_months', 1);

        $disk = $this->diskWithReport('202603', [
            '2026-03-31,id.rnq.triva,5,0,0,9000,5,0,6000',
        ]);

        $this->assertNull((new PlayReportsInstallsSource($disk))->fetch());
    }

    public function test_report_reader_ignores_a_file_without_the_total_column(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-31T00:00:00+00:00'));

        $disk = Storage::fake('play-reports');
        $disk->put(
            'stats/installs/installs_id.rnq.triva_202608_overview.csv',
            $this->utf16("Date,Package Name,Active Device Installs\n2026-08-30,id.rnq.triva,8112\n"),
        );

        $this->assertNull((new PlayReportsInstallsSource($disk))->fetch());
    }

    public function test_report_reader_returns_null_when_the_bucket_is_not_configured(): void
    {
        config()->set('play_store.installs.reports_bucket', null);

        $this->assertNull((new PlayReportsInstallsSource)->fetch());
    }

    public function test_service_prefers_the_report_and_keeps_manual_as_fallback(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-31T00:00:00+00:00'));
        config()->set('play_store.installs.source', 'play_reports');

        $this->manual('900', '2026-08-01');

        $withReport = new PlayStoreInstallsService(
            new ManualInstallsSource,
            new PlayReportsInstallsSource($this->diskWithReport('202608', [
                '2026-08-30,id.rnq.triva,12,0,0,12482,12,0,8112',
            ])),
        );

        $summary = $withReport->summarize();
        $this->assertSame(12482, $summary['total_installs']);
        $this->assertSame('play_reports', $summary['source']);

        // Bucket kosong: panel admin tetap menampilkan angka manual.
        $withoutReport = new PlayStoreInstallsService(
            new ManualInstallsSource,
            new PlayReportsInstallsSource(Storage::fake('empty-reports')),
        );

        $summary = $withoutReport->summarize();
        $this->assertSame(900, $summary['total_installs']);
        $this->assertSame('manual', $summary['source']);
        $this->assertTrue($summary['configured']);
    }

    public function test_service_never_reads_the_report_while_the_source_is_manual(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-31T00:00:00+00:00'));
        config()->set('play_store.installs.source', 'manual');

        $this->manual('900', '2026-08-01');

        $service = new PlayStoreInstallsService(
            new ManualInstallsSource,
            new PlayReportsInstallsSource($this->diskWithReport('202608', [
                '2026-08-30,id.rnq.triva,12,0,0,12482,12,0,8112',
            ])),
        );

        $summary = $service->summarize();
        $this->assertSame(900, $summary['total_installs']);
        $this->assertSame('manual', $summary['source']);
    }

    public function test_service_caches_the_resolved_number(): void
    {
        config()->set('play_store.installs.cache_ttl', 3600);
        config()->set('play_store.installs.source', 'manual');

        $service = new PlayStoreInstallsService(
            new ManualInstallsSource,
            new PlayReportsInstallsSource(Storage::fake('unused-reports')),
        );

        $this->manual('100', null);
        $this->assertSame(100, $service->summarize()['total_installs']);

        // Nilai baru belum terlihat sampai cache dibuang.
        AppConfig::query()->where('key', ManualInstallsSource::TOTAL_KEY)
            ->update(['value' => '200']);
        AppConfig::bustCache(ManualInstallsSource::TOTAL_KEY);
        $this->assertSame(100, $service->summarize()['total_installs']);

        PlayStoreInstallsService::bustCache();
        $this->assertSame(200, $service->summarize()['total_installs']);
    }

    /** @param  array<int, string>  $rows */
    private function diskWithReport(string $month, array $rows): Filesystem
    {
        $disk = Storage::fake('play-reports-'.$month);
        $disk->put(
            "stats/installs/installs_id.rnq.triva_{$month}_overview.csv",
            $this->utf16(self::HEADER."\n".implode("\n", $rows)."\n"),
        );

        return $disk;
    }

    /** Play Console menerbitkan laporannya sebagai UTF-16LE ber-BOM. */
    private function utf16(string $text): string
    {
        return "\xFF\xFE".mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    }

    private function manual(string $total, ?string $reportedAt): void
    {
        AppConfig::query()->updateOrCreate(
            ['key' => ManualInstallsSource::TOTAL_KEY],
            ['value' => $total, 'type' => AppConfigType::String],
        );
        AppConfig::query()->updateOrCreate(
            ['key' => ManualInstallsSource::REPORTED_AT_KEY],
            ['value' => (string) $reportedAt, 'type' => AppConfigType::String],
        );

        AppConfig::bustCache();
        AppConfig::bustCache(ManualInstallsSource::TOTAL_KEY);
        AppConfig::bustCache(ManualInstallsSource::REPORTED_AT_KEY);
        PlayStoreInstallsService::bustCache();
    }
}
