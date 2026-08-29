<?php

namespace Tests\Feature\Api;

use App\Models\MenuUsageEvent;
use App\Models\User;
use App\Support\Enums\Gender;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_analytics_endpoints_require_authentication_and_permission(): void
    {
        foreach ([
            '/api/v1/admin/analytics/demographics',
            '/api/v1/admin/analytics/menu-usage',
        ] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Passport::actingAs($staff);

        foreach ([
            '/api/v1/admin/analytics/demographics',
            '/api/v1/admin/analytics/menu-usage',
        ] as $path) {
            $this->getJson($path)->assertForbidden();
        }
    }

    public function test_demographics_group_gender_and_age_including_missing_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-29T05:00:00+00:00'));

        User::factory()->create([
            'gender' => Gender::Male,
            'birth_date' => '2004-01-01', // 22 tahun
        ]);
        User::factory()->create([
            'gender' => Gender::Male,
            'birth_date' => '1990-01-01', // 36 tahun
        ]);
        User::factory()->create([
            'gender' => Gender::Female,
            'birth_date' => '1996-09-01', // 29 tahun
        ]);
        User::factory()->create(['gender' => null, 'birth_date' => null]);

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/analytics/demographics')
            ->assertOk()
            // Empat pelanggan di atas ditambah akun admin dari setUp.
            ->assertJsonPath('data.total_users', 5)
            ->assertJsonPath('data.completed_profiles', 3);

        $gender = collect($response->json('data.gender'))->keyBy('key');
        $this->assertSame(2, $gender['male']['total']);
        $this->assertSame(1, $gender['female']['total']);
        $this->assertSame(0, $gender['undisclosed']['total']);
        $this->assertSame(2, $gender['unknown']['total']);

        $ages = collect($response->json('data.age_groups'))->keyBy('key');
        $this->assertSame(1, $ages['under_25']['total']);
        $this->assertSame(1, $ages['25_34']['total']);
        $this->assertSame(1, $ages['35_44']['total']);
        $this->assertSame(0, $ages['45_54']['total']);
        $this->assertSame(0, $ages['55_plus']['total']);
        $this->assertSame(2, $ages['unknown']['total']);
        $this->assertSame(0.6, $response->json('data.completion_rate'));
    }

    public function test_menu_usage_ranks_menus_per_period_using_jakarta_boundaries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-19T05:00:00+00:00'));

        // 2026-08-18T17:00Z = 2026-08-19 00:00 WIB, jadi masuk hitungan harian.
        $this->usage('appraisal', '2026-08-18T17:00:00+00:00');
        $this->usage('appraisal', '2026-08-19T01:00:00+00:00');
        $this->usage('credit', '2026-08-19T02:00:00+00:00');
        // 2026-08-18T16:59:59Z masih 18 Agustus WIB: mingguan, bukan harian.
        $this->usage('credit', '2026-08-18T16:59:59+00:00');
        // 2026-07-31T17:00Z = 1 Agustus 00:00 WIB, tepat di awal bulan.
        $this->usage('body_paint', '2026-07-31T17:00:00+00:00');
        // Sebelum awal bulan WIB: hanya masuk hitungan keseluruhan.
        $this->usage('otoxpert', '2026-07-31T16:59:59+00:00');

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/analytics/menu-usage')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.periods.daily.total', 3)
            ->assertJsonPath('data.periods.weekly.total', 4)
            ->assertJsonPath('data.periods.monthly.total', 5)
            ->assertJsonPath('data.periods.overall.total', 6);

        $this->assertSame(
            ['appraisal', 'credit'],
            array_column($response->json('data.periods.daily.menus'), 'key'),
        );
        $this->assertSame(
            'Taksir Harga Mobil',
            $response->json('data.periods.daily.menus.0.label'),
        );
        $this->assertSame(
            2,
            $response->json('data.periods.daily.menus.0.total'),
        );
        $this->assertSame(
            4,
            $response->json('data.periods.overall.distinct_menus'),
        );
    }

    public function test_menu_usage_reports_empty_state_without_events(): void
    {
        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/analytics/menu-usage')
            ->assertOk()
            ->assertJsonPath('data.tracking_started_at', null)
            ->assertJsonPath('data.periods.overall.total', 0)
            ->assertJsonPath('data.periods.overall.menus', []);
    }

    private function usage(string $menuKey, string $occurredAt): void
    {
        MenuUsageEvent::factory()->create([
            'menu_key' => $menuKey,
            'occurred_at' => CarbonImmutable::parse($occurredAt),
        ]);
    }
}
