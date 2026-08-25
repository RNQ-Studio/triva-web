<?php

namespace Tests\Feature\Api;

use App\Services\AppReleaseStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menjalankan AppReleaseStorage terhadap Flysystem sungguhan.
 *
 * AppReleaseApiTest memalsukan service ini, jadi tidak ada yang membuktikan
 * penanganan stream-nya benar. Flysystem mengambil alih stream yang dioper ke
 * put() dan menutupnya sendiri; menutupnya sekali lagi melempar TypeError yang
 * hanya muncul di produksi.
 */
class AppReleaseStorageTest extends TestCase
{
    public function test_it_uploads_the_binary_and_returns_a_download_url(): void
    {
        Storage::fake('gcs');
        config(['app_update.disk' => 'gcs']);

        $source = tempnam(sys_get_temp_dir(), 'apk');
        file_put_contents($source, "PK\x03\x04".str_repeat("\x00", 128));

        $url = app(AppReleaseStorage::class)->upload(
            $source,
            'app-releases/android/13/triva-1.0.12+13.apk',
        );

        Storage::disk('gcs')->assertExists('app-releases/android/13/triva-1.0.12+13.apk');
        $this->assertStringContainsString('triva-1.0.12+13.apk', $url);
        $this->assertSame(
            file_get_contents($source),
            Storage::disk('gcs')->get('app-releases/android/13/triva-1.0.12+13.apk'),
        );

        unlink($source);
    }

    public function test_an_unreadable_source_fails_loudly_instead_of_silently(): void
    {
        Storage::fake('gcs');
        config(['app_update.disk' => 'gcs']);

        $this->expectException(\RuntimeException::class);

        app(AppReleaseStorage::class)->upload(
            '/nonexistent/path/to/app.apk',
            'app-releases/android/13/app.apk',
        );
    }
}
