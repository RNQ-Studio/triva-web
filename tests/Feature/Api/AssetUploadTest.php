<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AssetUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('gcs');

        $client = app(ClientRepository::class)->createPasswordGrantClient('Test Password Grant', 'users', true);
        config([
            'passport.password_client.id' => $client->id,
            'passport.password_client.secret' => $client->plainSecret,
        ]);

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolePermissionSeeder::class);

        User::factory()->create(['email' => 'uploader@example.com']);

        $this->accessToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'uploader@example.com',
            'password' => 'password',
        ])->json('data.access_token');
    }

    public function test_user_can_upload_image_to_gcs(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->withToken($this->accessToken)
            ->postJson('/api/v1/assets/upload', [
                'file' => $file,
                'type' => 'user',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['id', 'original_filename', 'mime_type', 'size', 'public_url', 'status', 'retain_until', 'metadata', 'created_at'],
            ]);

        $assetId = $response->json('data.id');

        $this->assertDatabaseHas('assets', [
            'id' => $assetId,
            'storage_type' => 'gcs',
            'status' => 'active',
            'category' => 'user',
        ]);

        // Metadata image diekstrak otomatis.
        $this->assertSame(800, $response->json('data.metadata.width'));
        $this->assertSame(600, $response->json('data.metadata.height'));

        // File benar-benar tersimpan di disk gcs (faked).
        $path = Asset::find($assetId)->path;
        Storage::disk('gcs')->assertExists($path);
    }

    public function test_retain_until_null_marks_asset_permanent(): void
    {
        $file = UploadedFile::fake()->image('permanent.png');

        $response = $this->withToken($this->accessToken)
            ->postJson('/api/v1/assets/upload', [
                'file' => $file,
                'type' => 'user',
            ])
            ->assertCreated();

        $this->assertNull($response->json('data.retain_until'));
    }

    public function test_appraisal_photo_can_use_private_local_storage_without_cloud_billing(): void
    {
        Storage::forgetDisk('local');
        config(['filesystems.asset_upload_disk' => 'local']);

        $file = UploadedFile::fake()->image('front.jpg', 1280, 720);

        $response = $this->withToken($this->accessToken)
            ->postJson('/api/v1/assets/upload', [
                'file' => $file,
                'type' => 'appraisal-photo',
                'is_protected' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.public_url', null)
            ->assertJsonPath(
                'data.temporary_url',
                fn (mixed $url): bool => is_string($url)
                    && str_contains($url, '/storage/'),
            );

        $asset = Asset::query()->findOrFail($response->json('data.id'));
        $this->assertSame('private_local', $asset->storage_type->value);
        $this->assertTrue($asset->is_protected);
        Storage::disk('local')->assertExists($asset->path);
        Storage::disk('local')->delete($asset->path);
    }

    public function test_unprotected_asset_uses_public_disk_when_local_fallback_is_active(): void
    {
        Storage::fake('public');
        config(['filesystems.asset_upload_disk' => 'local']);

        $response = $this->withToken($this->accessToken)
            ->postJson('/api/v1/assets/upload', [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
                'type' => 'user',
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.public_url',
                fn (mixed $url): bool => is_string($url)
                    && str_contains($url, '/storage/'),
            );

        $asset = Asset::query()->findOrFail($response->json('data.id'));
        $this->assertSame('local', $asset->storage_type->value);
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_upload_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');

        $this->postJson('/api/v1/assets/upload', [
            'file' => $file,
            'type' => 'user',
        ])->assertUnauthorized();
    }

    public function test_upload_validates_required_fields(): void
    {
        $this->withToken($this->accessToken)
            ->postJson('/api/v1/assets/upload', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file', 'type']);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        // 60MB > 50MB limit
        $file = UploadedFile::fake()->create('big.pdf', 60 * 1024, 'application/pdf');

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/assets/upload', [
                'file' => $file,
                'type' => 'user',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/assets/upload', [
                'file' => $file,
                'type' => 'user',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }
}
