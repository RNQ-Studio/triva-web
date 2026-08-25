<?php

namespace Tests\Feature\Api;

use App\Models\AppRelease;
use App\Models\AppVersion;
use App\Services\AppReleaseStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AppReleaseApiTest extends TestCase
{
    use RefreshDatabase;

    private const UPLOAD_KEY = 'release-upload-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app_update.upload_key' => self::UPLOAD_KEY]);
        $this->app->instance(AppReleaseStorage::class, new FakeAppReleaseStorage);
    }

    public function test_upload_is_rejected_without_a_matching_key(): void
    {
        $response = $this->postJson('/api/v1/app/releases', [
            'file' => $this->fakeApk(),
            'version_code' => 12,
            'version_name' => '1.0.11',
        ], ['X-App-Release-Key' => 'wrong-key']);

        $response->assertStatus(403);
        $this->assertDatabaseCount('app_releases', 0);
    }

    public function test_upload_is_disabled_entirely_when_no_key_is_configured(): void
    {
        config(['app_update.upload_key' => '']);

        $response = $this->postJson('/api/v1/app/releases', [
            'file' => $this->fakeApk(),
            'version_code' => 12,
            'version_name' => '1.0.11',
        ], ['X-App-Release-Key' => '']);

        $response->assertStatus(403);
        $this->assertDatabaseCount('app_releases', 0);
    }

    public function test_upload_records_metadata_and_activates_the_new_release(): void
    {
        $previous = AppRelease::create([
            'platform' => 'android',
            'version_code' => 11,
            'version_name' => '1.0.10',
            'apk_url' => 'https://storage.example/app-releases/android/11/triva-1.0.10+11.apk',
            'apk_sha256' => str_repeat('a', 64),
            'apk_size_bytes' => 1024,
            'storage_path' => 'app-releases/android/11/triva-1.0.10+11.apk',
            'is_active' => true,
        ]);

        $apk = $this->fakeApk();
        $response = $this->post('/api/v1/app/releases', [
            'file' => $apk,
            'version_code' => 12,
            'version_name' => '1.0.11',
            'release_notes' => 'Beranda baru dan palet monokrom.',
        ], ['X-App-Release-Key' => self::UPLOAD_KEY]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version_code', 12)
            ->assertJsonPath('data.version_name', '1.0.11')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.apk_filename', 'triva-1.0.11+12.apk')
            ->assertJsonPath(
                'data.apk_url',
                'https://storage.test/app-releases/android/12/triva-1.0.11+12.apk',
            );

        $this->assertSame(
            hash('sha256', $this->apkBytes()),
            $response->json('data.apk_sha256'),
        );

        $this->assertFalse($previous->fresh()->is_active);
        $this->assertSame('1.0.11', AppVersion::query()->sole()->latest_version);
    }

    public function test_upload_does_not_force_update_when_it_creates_the_version_policy(): void
    {
        $this->post('/api/v1/app/releases', [
            'file' => $this->fakeApk(),
            'version_code' => 12,
            'version_name' => '1.0.11',
        ], ['X-App-Release-Key' => self::UPLOAD_KEY])->assertStatus(201);

        $policy = AppVersion::query()->sole();
        $this->assertFalse($policy->force_update);
        $this->assertSame('1.0.0', $policy->min_version);
    }

    public function test_upload_keeps_an_existing_minimum_version_untouched(): void
    {
        AppVersion::create([
            'platform' => 'android',
            'min_version' => '1.0.5',
            'latest_version' => '1.0.10',
            'force_update' => true,
        ]);

        $this->post('/api/v1/app/releases', [
            'file' => $this->fakeApk(),
            'version_code' => 12,
            'version_name' => '1.0.11',
        ], ['X-App-Release-Key' => self::UPLOAD_KEY])->assertStatus(201);

        $policy = AppVersion::query()->sole();
        $this->assertSame('1.0.5', $policy->min_version);
        $this->assertSame('1.0.11', $policy->latest_version);
        $this->assertTrue($policy->force_update);
    }

    public function test_a_file_that_is_not_an_archive_is_rejected(): void
    {
        $response = $this->post('/api/v1/app/releases', [
            'file' => UploadedFile::fake()->createWithContent('app.apk', 'not-an-archive'),
            'version_code' => 12,
            'version_name' => '1.0.11',
        ], ['X-App-Release-Key' => self::UPLOAD_KEY]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('app_releases', 0);
    }

    public function test_a_version_code_that_does_not_advance_is_rejected(): void
    {
        AppRelease::create([
            'platform' => 'android',
            'version_code' => 12,
            'version_name' => '1.0.11',
            'apk_url' => 'https://storage.example/a.apk',
            'apk_sha256' => str_repeat('b', 64),
            'apk_size_bytes' => 2048,
            'storage_path' => 'app-releases/android/12/a.apk',
            'is_active' => true,
        ]);

        $response = $this->post('/api/v1/app/releases', [
            'file' => $this->fakeApk(),
            'version_code' => 12,
            'version_name' => '1.0.11',
        ], ['X-App-Release-Key' => self::UPLOAD_KEY]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('app_releases', 1);
    }

    public function test_latest_returns_the_active_release(): void
    {
        AppRelease::create([
            'platform' => 'android',
            'version_code' => 11,
            'version_name' => '1.0.10',
            'apk_url' => 'https://storage.example/old.apk',
            'apk_sha256' => str_repeat('c', 64),
            'apk_size_bytes' => 10,
            'storage_path' => 'app-releases/android/11/old.apk',
            'is_active' => false,
        ]);
        AppRelease::create([
            'platform' => 'android',
            'version_code' => 12,
            'version_name' => '1.0.11',
            'apk_url' => 'https://storage.example/new.apk',
            'apk_sha256' => str_repeat('d', 64),
            'apk_size_bytes' => 20,
            'storage_path' => 'app-releases/android/12/new.apk',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/app/releases/latest')
            ->assertOk()
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.release.version_code', 12)
            ->assertJsonPath('data.release.apk_sha256', str_repeat('d', 64));
    }

    public function test_latest_reports_no_release_instead_of_failing(): void
    {
        $this->getJson('/api/v1/app/releases/latest')
            ->assertOk()
            ->assertJsonPath('data.release', null);
    }

    private function fakeApk(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('app-release.apk', $this->apkBytes());
    }

    private function apkBytes(): string
    {
        // Signature ZIP local file header — bentuk minimum yang lolos
        // pemeriksaan "benar-benar arsip" pada controller.
        return "PK\x03\x04".str_repeat("\x00", 64);
    }
}

class FakeAppReleaseStorage extends AppReleaseStorage
{
    public function upload(string $realPath, string $objectPath): string
    {
        return 'https://storage.test/'.$objectPath;
    }
}
