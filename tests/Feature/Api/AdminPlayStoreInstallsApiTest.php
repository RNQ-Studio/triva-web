<?php

namespace Tests\Feature\Api;

use App\Models\AppConfig;
use App\Models\User;
use App\Services\PlayStoreInstallsService;
use App\Support\Enums\AppConfigType;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminPlayStoreInstallsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        PlayStoreInstallsService::bustCache();
    }

    public function test_play_store_installs_requires_authentication_and_analytics_permission(): void
    {
        $this->getJson('/api/v1/admin/analytics/play-store-installs')
            ->assertUnauthorized();

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Passport::actingAs($staff);

        $this->getJson('/api/v1/admin/analytics/play-store-installs')
            ->assertForbidden();
    }

    public function test_installs_report_as_unconfigured_while_the_number_is_blank(): void
    {
        $this->config('play_store_total_installs', '');

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/analytics/play-store-installs')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.total_installs', null)
            ->assertJsonPath('data.source', null)
            ->assertJsonPath('data.package_name', 'id.rnq.triva');
    }

    public function test_manual_number_is_served_with_its_reporting_date(): void
    {
        $this->config('play_store_total_installs', '12480');
        $this->config('play_store_installs_reported_at', '2026-08-30');

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/analytics/play-store-installs')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.total_installs', 12480)
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.reported_at', '2026-08-30T00:00:00+00:00');
    }

    public function test_zero_installs_stay_distinguishable_from_a_blank_number(): void
    {
        $this->config('play_store_total_installs', '0');

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/analytics/play-store-installs')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.total_installs', 0);
    }

    public function test_a_mistyped_number_is_treated_as_unconfigured(): void
    {
        $this->config('play_store_total_installs', '12.480 unduhan');

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/analytics/play-store-installs')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.total_installs', null);
    }

    public function test_a_mistyped_date_keeps_the_number_usable(): void
    {
        $this->config('play_store_total_installs', '77');
        $this->config('play_store_installs_reported_at', 'kemarin sore');

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/analytics/play-store-installs')
            ->assertOk()
            ->assertJsonPath('data.total_installs', 77)
            ->assertJsonPath('data.reported_at', null);
    }

    public function test_generated_at_reports_the_current_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-31T04:00:00+00:00'));
        $this->config('play_store_total_installs', '5');

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/analytics/play-store-installs')
            ->assertOk()
            ->assertJsonPath('data.generated_at', '2026-08-31T04:00:00+00:00');
    }

    private function config(string $key, string $value): void
    {
        AppConfig::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => AppConfigType::String],
        );

        AppConfig::bustCache($key);
        PlayStoreInstallsService::bustCache();
    }
}
