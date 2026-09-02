<?php

namespace Tests\Feature\Api;

use App\Models\AppConfig;
use App\Support\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function enableViaEnv(array $overrides = []): void
    {
        config(array_merge(['maintenance.enabled' => true], $overrides));
    }

    // ── Sakelar dari .env ────────────────────────────────────────────────────

    public function test_env_switch_blocks_api_without_any_database_row(): void
    {
        $this->enableViaEnv(['maintenance.message' => 'Sistem sedang dimatikan sementara.']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'secret'])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'MAINTENANCE_MODE')
            ->assertJsonPath('message', 'Sistem sedang dimatikan sementara.');
    }

    public function test_env_switch_wins_over_database_flag(): void
    {
        AppConfig::create(['key' => 'maintenance_mode', 'value' => 'true', 'type' => 'boolean']);
        config(['maintenance.enabled' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'secret'])
            ->assertStatus(401);
    }

    public function test_database_flag_still_works_when_env_is_unset(): void
    {
        config(['maintenance.enabled' => null]);
        AppConfig::create(['key' => 'maintenance_mode', 'value' => 'true', 'type' => 'boolean']);
        AppConfig::create(['key' => 'maintenance_message', 'value' => 'Pesan dari back-office.', 'type' => 'string']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'secret'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Pesan dari back-office.');
    }

    public function test_env_message_wins_over_database_message(): void
    {
        AppConfig::create(['key' => 'maintenance_message', 'value' => 'Pesan lama.', 'type' => 'string']);
        $this->enableViaEnv(['maintenance.message' => 'Pesan dari .env.']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'secret'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Pesan dari .env.');
    }

    public function test_falls_back_to_default_message_when_nothing_is_configured(): void
    {
        $this->enableViaEnv(['maintenance.message' => null]);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'secret'])
            ->assertStatus(503)
            ->assertJsonPath('message', config('maintenance.default_message'));
    }

    public function test_system_is_reachable_when_switch_is_off(): void
    {
        config(['maintenance.enabled' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'secret'])
            ->assertStatus(401);
    }

    // ── Cakupan ──────────────────────────────────────────────────────────────

    /**
     * Sakelar dipasang pada grup `api`, bukan per-route. Route yang dulu tidak
     * pernah memasang alias `check.maintenance` pun harus ikut tertutup.
     */
    public function test_switch_covers_routes_that_never_declared_the_alias(): void
    {
        $this->enableViaEnv();

        $this->getJson('/api/v1/quotes')->assertStatus(503);
        $this->getJson('/api/v1/users/export')->assertStatus(503);
    }

    public function test_app_info_endpoints_stay_open(): void
    {
        $this->enableViaEnv();

        $this->getJson('/api/v1/app/config')->assertOk();
        $this->getJson('/api/v1/app/releases/latest')->assertSuccessful();
    }

    public function test_health_check_stays_open_for_monitoring(): void
    {
        $this->enableViaEnv();

        $this->getJson('/api/v1/health')->assertOk();
        $this->get('/up')->assertOk();
    }

    public function test_allowlisted_ip_passes_through(): void
    {
        $this->enableViaEnv(['maintenance.allow_ips' => ['127.0.0.1']]);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'secret'])
            ->assertStatus(401);
    }

    public function test_non_allowlisted_ip_is_still_blocked(): void
    {
        $this->enableViaEnv(['maintenance.allow_ips' => ['203.0.113.9']]);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'secret'])
            ->assertStatus(503);
    }

    // ── Retry-After ──────────────────────────────────────────────────────────

    public function test_response_carries_retry_after_header(): void
    {
        $this->enableViaEnv(['maintenance.retry_after' => 1800, 'maintenance.until' => null]);

        $response = $this->getJson('/api/v1/quotes')->assertStatus(503);

        $this->assertSame('1800', $response->headers->get('Retry-After'));
    }

    public function test_retry_after_is_derived_from_until_when_present(): void
    {
        $this->enableViaEnv(['maintenance.until' => now()->addMinutes(10)->toIso8601String()]);

        $seconds = (int) $this->getJson('/api/v1/quotes')->headers->get('Retry-After');

        $this->assertGreaterThan(500, $seconds);
        $this->assertLessThanOrEqual(600, $seconds);
    }

    public function test_retry_after_never_drops_below_a_minute_for_a_past_until(): void
    {
        $this->enableViaEnv([
            'maintenance.until' => now()->subHour()->toIso8601String(),
            'maintenance.retry_after' => 10,
        ]);

        $this->assertSame(60, Maintenance::retryAfterSeconds());
    }

    public function test_unparseable_until_is_ignored_instead_of_failing_the_request(): void
    {
        $this->enableViaEnv(['maintenance.until' => 'kapan-kapan']);

        $this->getJson('/api/v1/quotes')->assertStatus(503);
        $this->assertNull(Maintenance::until());
    }

    // ── Klien tetap bisa membaca statusnya ───────────────────────────────────

    public function test_app_config_endpoint_reports_maintenance_state(): void
    {
        $until = now()->addHours(2);
        $this->enableViaEnv([
            'maintenance.message' => 'Kami sedang memperbaiki sistem.',
            'maintenance.title' => 'Sedang Perawatan',
            'maintenance.until' => $until->toIso8601String(),
        ]);

        $this->getJson('/api/v1/app/config')
            ->assertOk()
            ->assertJsonPath('data.maintenance_mode', true)
            ->assertJsonPath('data.maintenance_title', 'Sedang Perawatan')
            ->assertJsonPath('data.maintenance_message', 'Kami sedang memperbaiki sistem.')
            ->assertJsonPath('data.maintenance_until', $until->toIso8601String());
    }

    public function test_app_config_maintenance_state_overrides_stale_database_row(): void
    {
        AppConfig::create(['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean']);
        $this->enableViaEnv();

        $this->getJson('/api/v1/app/config')
            ->assertOk()
            ->assertJsonPath('data.maintenance_mode', true);
    }

    public function test_app_config_reports_healthy_state_when_switch_is_off(): void
    {
        config(['maintenance.enabled' => false]);

        $this->getJson('/api/v1/app/config')
            ->assertOk()
            ->assertJsonPath('data.maintenance_mode', false)
            ->assertJsonPath('data.maintenance_until', null);
    }

    // ── Ketahanan ────────────────────────────────────────────────────────────

    /**
     * Maintenance sering dinyalakan justru karena database bermasalah. Sakelar
     * dari `.env` tidak boleh ikut mati karena query fallback-nya gagal.
     */
    public function test_env_switch_works_without_touching_the_database(): void
    {
        Cache::flush();
        $this->enableViaEnv();

        AppConfig::query()->getConnection()->disconnect();

        $this->getJson('/api/v1/quotes')->assertStatus(503);
    }
}
