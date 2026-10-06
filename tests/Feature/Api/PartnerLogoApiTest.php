<?php

namespace Tests\Feature\Api;

use App\Models\PartnerLogo;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PartnerLogoApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_only_active_logos_are_listed_in_display_order(): void
    {
        PartnerLogo::factory()->create(['name' => 'TAF', 'sort_order' => 2]);
        PartnerLogo::factory()->create([
            'name' => 'Auto2000',
            'sort_order' => 1,
            'logo_path' => 'partner-logos/auto2000.png',
            'link_url' => 'https://auto2000.co.id',
        ]);
        PartnerLogo::factory()->create(['name' => 'ACC', 'sort_order' => 2]);
        PartnerLogo::factory()->create(['name' => 'Disembunyikan', 'is_active' => false]);

        $response = $this->getJson('/api/v1/partner-logos')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Auto2000')
            ->assertJsonPath('data.0.link_url', 'https://auto2000.co.id')
            ->assertJsonPath('data.0.sort_order', 1)
            ->assertJsonPath('data.1.name', 'ACC')
            ->assertJsonPath('data.2.name', 'TAF')
            ->assertJsonMissingPath('data.0.is_active');

        self::assertStringEndsWith(
            '/storage/partner-logos/auto2000.png',
            (string) $response->json('data.0.logo_url'),
        );
    }

    public function test_admin_endpoints_require_authentication_and_content_permission(): void
    {
        $this->getJson('/api/v1/admin/partner-logos')->assertUnauthorized();

        Passport::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/partner-logos')->assertForbidden();

        Passport::actingAs($this->admin);
        $this->getJson('/api/v1/admin/partner-logos')->assertOk();
    }

    public function test_admin_list_includes_hidden_logos(): void
    {
        PartnerLogo::factory()->create(['name' => 'Tampil', 'sort_order' => 1]);
        PartnerLogo::factory()->create(['name' => 'Tersembunyi', 'sort_order' => 2, 'is_active' => false]);

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/partner-logos')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.name', 'Tersembunyi')
            ->assertJsonPath('data.1.is_active', false);
    }

    public function test_admin_can_create_a_partner_logo(): void
    {
        Passport::actingAs($this->admin);

        $this->post('/api/v1/admin/partner-logos', [
            'name' => 'Toyota',
            'logo' => UploadedFile::fake()->image('toyota.png', 400, 200),
            'link_url' => 'https://www.toyota.astra.co.id',
            'sort_order' => '6',
            'is_active' => '1',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Toyota')
            ->assertJsonPath('data.link_url', 'https://www.toyota.astra.co.id')
            ->assertJsonPath('data.sort_order', 6)
            ->assertJsonPath('data.is_active', true);

        $logo = PartnerLogo::query()->sole();
        self::assertStringStartsWith('partner-logos/', $logo->logo_path);
        Storage::disk('public')->assertExists($logo->logo_path);
    }

    public function test_invalid_partner_input_is_rejected(): void
    {
        Passport::actingAs($this->admin);

        $this->post('/api/v1/admin/partner-logos', [
            'link_url' => 'ftp://mitra.example',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'logo', 'link_url']);

        self::assertSame(0, PartnerLogo::query()->count());
    }

    public function test_update_replaces_logo_and_can_clear_the_link(): void
    {
        Storage::disk('public')->put('partner-logos/lama.png', 'lama');
        $logo = PartnerLogo::factory()->create([
            'logo_path' => 'partner-logos/lama.png',
            'link_url' => 'https://auto2000.co.id',
        ]);

        Passport::actingAs($this->admin);

        $this->post('/api/v1/admin/partner-logos/'.$logo->getKey(), [
            'logo' => UploadedFile::fake()->image('baru.png'),
            'link_url' => '',
            'is_active' => 'false',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.link_url', null)
            ->assertJsonPath('data.is_active', false);

        $logo->refresh();
        Storage::disk('public')->assertMissing('partner-logos/lama.png');
        Storage::disk('public')->assertExists($logo->logo_path);
    }

    public function test_admin_can_delete_a_logo_and_its_file(): void
    {
        Storage::disk('public')->put('partner-logos/hapus.png', 'hapus');
        $logo = PartnerLogo::factory()->create(['logo_path' => 'partner-logos/hapus.png']);

        Passport::actingAs($this->admin);

        $this->deleteJson('/api/v1/admin/partner-logos/'.$logo->getKey())->assertOk();

        self::assertModelMissing($logo);
        Storage::disk('public')->assertMissing('partner-logos/hapus.png');
    }

    public function test_seed_migration_copies_the_built_in_partner_logos_once(): void
    {
        $migration = require database_path('migrations/2026_10_06_110100_seed_partner_logos.php');

        $migration->seed();
        $migration->seed();

        $logos = PartnerLogo::query()->orderBy('sort_order')->get();
        self::assertSame(
            ['Auto2000', 'OtoXpert', 'OLX', 'ACC', 'TAF'],
            $logos->pluck('name')->all(),
        );
        self::assertSame([1, 2, 3, 4, 5], $logos->pluck('sort_order')->all());
        self::assertSame('https://auto2000.co.id', $logos->first()?->link_url);
        foreach ($logos as $logo) {
            self::assertTrue($logo->is_active);
            Storage::disk('public')->assertExists($logo->logo_path);
        }
    }

    public function test_seed_migration_leaves_admin_managed_logos_untouched(): void
    {
        PartnerLogo::factory()->create(['name' => 'Diatur admin']);
        $migration = require database_path('migrations/2026_10_06_110100_seed_partner_logos.php');

        $migration->seed();

        self::assertSame(['Diatur admin'], PartnerLogo::query()->pluck('name')->all());
    }
}
