<?php

namespace Tests\Feature;

use App\Models\AppRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LatestAppReleaseDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redirects_to_the_active_release_binary(): void
    {
        $this->makeRelease(12, '1.0.11', isActive: false);
        $this->makeRelease(13, '1.0.12', isActive: true);

        $this->get('/app/release-latest')
            ->assertRedirect('https://storage.test/android/13/triva-1.0.12+13.apk');
    }

    public function test_a_superseded_release_is_never_offered(): void
    {
        // is_active menandai rilis yang ditawarkan; version code tertinggi
        // saja tidak cukup kalau rilis itu sudah dinonaktifkan.
        $this->makeRelease(13, '1.0.12', isActive: false);
        $this->makeRelease(12, '1.0.11', isActive: true);

        $this->get('/app/release-latest')
            ->assertRedirect('https://storage.test/android/12/triva-1.0.11+12.apk');
    }

    public function test_it_reports_not_found_when_nothing_has_been_published(): void
    {
        $this->get('/app/release-latest')->assertNotFound();
    }

    public function test_an_ios_only_catalogue_does_not_satisfy_the_android_link(): void
    {
        $this->makeRelease(13, '1.0.12', isActive: true, platform: 'ios');

        $this->get('/app/release-latest')->assertNotFound();
    }

    private function makeRelease(
        int $versionCode,
        string $versionName,
        bool $isActive,
        string $platform = 'android',
    ): AppRelease {
        $filename = 'triva-'.$versionName.'+'.$versionCode.'.apk';

        return AppRelease::create([
            'platform' => $platform,
            'version_code' => $versionCode,
            'version_name' => $versionName,
            'apk_url' => 'https://storage.test/'.$platform.'/'.$versionCode.'/'.$filename,
            'apk_sha256' => str_repeat('a', 64),
            'apk_size_bytes' => 1024,
            'storage_path' => 'app-releases/'.$platform.'/'.$versionCode.'/'.$filename,
            'is_active' => $isActive,
        ]);
    }
}
