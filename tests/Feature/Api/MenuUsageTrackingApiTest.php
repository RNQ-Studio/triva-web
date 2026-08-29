<?php

namespace Tests\Feature\Api;

use App\Models\MenuUsageEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MenuUsageTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_menu_usage_requires_authentication(): void
    {
        $this->postJson('/api/v1/analytics/menu-usage', [
            'menu_key' => 'appraisal',
            'source' => 'android',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('menu_usage_events', 0);
    }

    public function test_authenticated_customer_taps_are_recorded_per_event(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->postJson('/api/v1/analytics/menu-usage', [
            'menu_key' => 'appraisal',
            'source' => 'android',
            'app_version' => '1.2.0',
            'app_build' => '17',
        ])
            ->assertAccepted()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.menu_key', 'appraisal');

        $this->postJson('/api/v1/analytics/menu-usage', [
            'menu_key' => 'appraisal',
            'source' => 'android',
        ])->assertAccepted();

        // Frekuensi, bukan pemakai unik: ketukan berulang tetap dua baris.
        $this->assertDatabaseCount('menu_usage_events', 2);
        $event = MenuUsageEvent::query()->firstOrFail();
        $this->assertSame($user->getKey(), $event->user_id);
        $this->assertSame('1.2.0', $event->app_version);
    }

    public function test_unknown_menu_keys_are_accepted_but_malformed_ones_are_rejected(): void
    {
        Passport::actingAs(User::factory()->create());

        // Aplikasi yang lebih baru boleh mengirim menu yang belum dikenal.
        $this->postJson('/api/v1/analytics/menu-usage', [
            'menu_key' => 'menu_baru_belum_dikenal',
            'source' => 'web',
        ])->assertAccepted();

        $this->postJson('/api/v1/analytics/menu-usage', [
            'menu_key' => 'Menu Salah',
            'source' => 'web',
        ])->assertUnprocessable()->assertJsonValidationErrors(['menu_key']);

        $this->postJson('/api/v1/analytics/menu-usage', [
            'menu_key' => 'appraisal',
            'source' => 'landing_page',
        ])->assertUnprocessable()->assertJsonValidationErrors(['source']);

        $this->postJson('/api/v1/analytics/menu-usage', [
            'menu_key' => 'appraisal',
            'source' => 'android',
            'occurred_at' => now()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['occurred_at']);

        $this->assertDatabaseCount('menu_usage_events', 1);
    }
}
