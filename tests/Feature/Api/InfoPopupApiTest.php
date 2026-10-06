<?php

namespace Tests\Feature\Api;

use App\Models\InfoPopup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class InfoPopupApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-06 03:00:00', 'UTC'));
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_only_running_popups_are_listed_in_display_order(): void
    {
        InfoPopup::factory()->create(['title' => 'Kedua', 'sort_order' => 2]);
        InfoPopup::factory()->create([
            'title' => 'Pertama',
            'sort_order' => 1,
            'image_path' => 'info-popups/pertama.jpg',
            'button_label' => 'Lihat promo',
            'button_url' => 'https://auto2000.co.id/promo',
            'interval_hours' => 6,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
        ]);
        InfoPopup::factory()->create(['title' => 'Belum tayang', 'starts_on' => '2026-11-01']);
        InfoPopup::factory()->create(['title' => 'Sudah lewat', 'ends_on' => '2026-10-05']);
        InfoPopup::factory()->create(['title' => 'Nonaktif', 'is_active' => false]);

        $response = $this->getJson('/api/v1/info-popups')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Pertama')
            ->assertJsonPath('data.0.button_label', 'Lihat promo')
            ->assertJsonPath('data.0.button_url', 'https://auto2000.co.id/promo')
            ->assertJsonPath('data.0.interval_hours', 6)
            ->assertJsonPath('data.0.sort_order', 1)
            ->assertJsonPath('data.1.title', 'Kedua')
            ->assertJsonPath('data.1.button_label', null)
            ->assertJsonMissingPath('data.0.is_active');

        self::assertStringEndsWith(
            '/storage/info-popups/pertama.jpg',
            (string) $response->json('data.0.image_url'),
        );
        self::assertNotNull($response->json('data.0.updated_at'));
    }

    public function test_admin_endpoints_require_authentication_and_content_permission(): void
    {
        $this->getJson('/api/v1/admin/info-popups')->assertUnauthorized();

        Passport::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/info-popups')->assertForbidden();
        $this->post('/api/v1/admin/info-popups', [
            'title' => 'Tanpa izin',
            'image' => UploadedFile::fake()->image('popup.jpg'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        Passport::actingAs($this->admin);
        $this->getJson('/api/v1/admin/info-popups')->assertOk();
    }

    public function test_admin_list_includes_inactive_popups_with_schedule_state(): void
    {
        InfoPopup::factory()->create(['title' => 'Tayang', 'sort_order' => 1]);
        InfoPopup::factory()->create([
            'title' => 'Nonaktif',
            'sort_order' => 2,
            'is_active' => false,
            'starts_on' => '2026-10-01',
        ]);

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/info-popups')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Tayang')
            ->assertJsonPath('data.0.is_running', true)
            ->assertJsonPath('data.1.is_active', false)
            ->assertJsonPath('data.1.is_running', false)
            ->assertJsonPath('data.1.starts_on', '2026-10-01');
    }

    public function test_admin_can_create_a_popup_with_image_and_button(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->post('/api/v1/admin/info-popups', [
            'title' => 'Promo servis Oktober',
            'image' => UploadedFile::fake()->image('popup.jpg', 1080, 1350),
            'button_label' => 'Booking sekarang',
            'button_url' => 'https://auto2000.co.id/booking',
            'sort_order' => '3',
            'interval_hours' => '12',
            'is_active' => 'true',
            'starts_on' => '2026-10-06',
            'ends_on' => '2026-10-31',
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Promo servis Oktober')
            ->assertJsonPath('data.button_label', 'Booking sekarang')
            ->assertJsonPath('data.sort_order', 3)
            ->assertJsonPath('data.interval_hours', 12)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_running', true)
            ->assertJsonPath('data.ends_on', '2026-10-31');

        $popup = InfoPopup::query()->sole();
        self::assertStringStartsWith('info-popups/', $popup->image_path);
        Storage::disk('public')->assertExists($popup->image_path);
    }

    public function test_defaults_apply_when_optional_fields_are_omitted(): void
    {
        Passport::actingAs($this->admin);

        $this->post('/api/v1/admin/info-popups', [
            'title' => 'Info singkat',
            'image' => UploadedFile::fake()->image('popup.png'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 0)
            ->assertJsonPath('data.interval_hours', 24)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.button_label', null)
            ->assertJsonPath('data.button_url', null);
    }

    public function test_button_label_and_url_must_be_filled_together(): void
    {
        Passport::actingAs($this->admin);

        $this->post('/api/v1/admin/info-popups', [
            'title' => 'Tombol setengah',
            'image' => UploadedFile::fake()->image('popup.jpg'),
            'button_label' => 'Lihat',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['button_url']);

        $this->post('/api/v1/admin/info-popups', [
            'title' => 'Tautan tanpa label',
            'image' => UploadedFile::fake()->image('popup.jpg'),
            'button_url' => 'https://auto2000.co.id',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['button_label']);

        self::assertSame(0, InfoPopup::query()->count());
    }

    public function test_invalid_interval_url_scheme_window_and_file_are_rejected(): void
    {
        Passport::actingAs($this->admin);

        $this->post('/api/v1/admin/info-popups', [
            'title' => 'Salah semua',
            'image' => UploadedFile::fake()->create('popup.pdf', 10, 'application/pdf'),
            'button_label' => 'Buka',
            'button_url' => 'javascript:alert(1)',
            'interval_hours' => '0',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image', 'button_url', 'interval_hours']);

        $this->post('/api/v1/admin/info-popups', [
            'title' => 'Terlalu jarang',
            'image' => UploadedFile::fake()->image('popup.jpg'),
            'interval_hours' => '721',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['interval_hours']);

        $this->post('/api/v1/admin/info-popups', [
            'title' => 'Jadwal terbalik',
            'image' => UploadedFile::fake()->image('popup.jpg'),
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-01',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_on']);

        self::assertSame(0, InfoPopup::query()->count());
        self::assertSame([], Storage::disk('public')->allFiles('info-popups'));
    }

    public function test_update_replaces_the_image_and_removes_the_old_file(): void
    {
        Storage::disk('public')->put('info-popups/lama.jpg', 'lama');
        $popup = InfoPopup::factory()->create([
            'image_path' => 'info-popups/lama.jpg',
            'button_label' => 'Lihat',
            'button_url' => 'https://auto2000.co.id',
        ]);

        Passport::actingAs($this->admin);

        $this->post('/api/v1/admin/info-popups/'.$popup->getKey(), [
            'image' => UploadedFile::fake()->image('baru.jpg'),
            'sort_order' => '5',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 5)
            ->assertJsonPath('data.button_label', 'Lihat');

        $popup->refresh();
        self::assertNotSame('info-popups/lama.jpg', $popup->image_path);
        Storage::disk('public')->assertMissing('info-popups/lama.jpg');
        Storage::disk('public')->assertExists($popup->image_path);
    }

    public function test_update_can_clear_the_button_and_keeps_the_image(): void
    {
        Storage::disk('public')->put('info-popups/tetap.jpg', 'tetap');
        $popup = InfoPopup::factory()->create([
            'image_path' => 'info-popups/tetap.jpg',
            'button_label' => 'Lihat',
            'button_url' => 'https://auto2000.co.id',
        ]);

        Passport::actingAs($this->admin);

        $this->post('/api/v1/admin/info-popups/'.$popup->getKey(), [
            'button_label' => '',
            'button_url' => '',
            'is_active' => 'false',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.button_label', null)
            ->assertJsonPath('data.button_url', null)
            ->assertJsonPath('data.is_active', false);

        Storage::disk('public')->assertExists('info-popups/tetap.jpg');
    }

    public function test_update_cannot_leave_half_a_button(): void
    {
        $popup = InfoPopup::factory()->create([
            'button_label' => 'Lihat',
            'button_url' => 'https://auto2000.co.id',
        ]);

        Passport::actingAs($this->admin);

        $this->post('/api/v1/admin/info-popups/'.$popup->getKey(), [
            'button_url' => '',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['button_url']);
    }

    public function test_admin_can_delete_a_popup_and_its_image(): void
    {
        Storage::disk('public')->put('info-popups/hapus.jpg', 'hapus');
        $popup = InfoPopup::factory()->create(['image_path' => 'info-popups/hapus.jpg']);

        Passport::actingAs($this->admin);

        $this->deleteJson('/api/v1/admin/info-popups/'.$popup->getKey())->assertOk();

        self::assertModelMissing($popup);
        Storage::disk('public')->assertMissing('info-popups/hapus.jpg');
    }

    public function test_staff_without_delete_permission_cannot_delete(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $popup = InfoPopup::factory()->create();

        Passport::actingAs($staff);

        $this->getJson('/api/v1/admin/info-popups')->assertOk();
        $this->deleteJson('/api/v1/admin/info-popups/'.$popup->getKey())->assertForbidden();
        self::assertModelExists($popup);
    }
}
