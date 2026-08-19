<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VisitEvent;
use App\Support\Enums\VisitSource;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminVisitStatisticsApiTest extends TestCase
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

    public function test_visit_statistics_requires_authentication_and_analytics_permission(): void
    {
        $this->getJson('/api/v1/admin/analytics/visits')->assertUnauthorized();

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Passport::actingAs($staff);

        $this->getJson('/api/v1/admin/analytics/visits')->assertForbidden();

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/analytics/visits')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.tracking_started_at', null)
            ->assertJsonPath('data.periods.daily.total', 0)
            ->assertJsonPath('data.periods.overall.by_source.android', 0)
            ->assertJsonPath('data.periods.overall.by_source.web', 0)
            ->assertJsonPath('data.periods.overall.by_source.landing_page', 0);
    }

    public function test_visit_statistics_use_jakarta_calendar_boundaries_and_group_every_source(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-19T05:00:00+00:00'));

        $this->visit(VisitSource::Android, '2026-08-18T17:00:00+00:00');
        $this->visit(VisitSource::Web, '2026-08-19T01:00:00+00:00');
        $this->visit(VisitSource::LandingPage, '2026-08-18T16:59:59+00:00');
        $this->visit(VisitSource::Android, '2026-08-16T17:00:00+00:00');
        $this->visit(VisitSource::Web, '2026-08-16T16:59:59+00:00');
        $this->visit(VisitSource::LandingPage, '2026-07-31T17:00:00+00:00');
        $this->visit(VisitSource::Android, '2026-07-31T16:59:59+00:00');

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/analytics/visits')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.generated_at', '2026-08-19T05:00:00+00:00')
            ->assertJsonPath('data.tracking_started_at', '2026-07-31T16:59:59+00:00')
            ->assertJsonPath('data.periods.daily.starts_at', '2026-08-18T17:00:00+00:00')
            ->assertJsonPath('data.periods.daily.ends_at', '2026-08-19T05:00:00+00:00')
            ->assertJsonPath('data.periods.daily.total', 2)
            ->assertJsonPath('data.periods.daily.by_source.android', 1)
            ->assertJsonPath('data.periods.daily.by_source.web', 1)
            ->assertJsonPath('data.periods.daily.by_source.landing_page', 0)
            ->assertJsonPath('data.periods.weekly.starts_at', '2026-08-16T17:00:00+00:00')
            ->assertJsonPath('data.periods.weekly.total', 4)
            ->assertJsonPath('data.periods.weekly.by_source.android', 2)
            ->assertJsonPath('data.periods.weekly.by_source.web', 1)
            ->assertJsonPath('data.periods.weekly.by_source.landing_page', 1)
            ->assertJsonPath('data.periods.monthly.starts_at', '2026-07-31T17:00:00+00:00')
            ->assertJsonPath('data.periods.monthly.total', 6)
            ->assertJsonPath('data.periods.monthly.by_source.android', 2)
            ->assertJsonPath('data.periods.monthly.by_source.web', 2)
            ->assertJsonPath('data.periods.monthly.by_source.landing_page', 2)
            ->assertJsonPath('data.periods.overall.starts_at', '2026-07-31T16:59:59+00:00')
            ->assertJsonPath('data.periods.overall.total', 7)
            ->assertJsonPath('data.periods.overall.by_source.android', 3)
            ->assertJsonPath('data.periods.overall.by_source.web', 2)
            ->assertJsonPath('data.periods.overall.by_source.landing_page', 2);
    }

    private function visit(VisitSource $source, string $occurredAt): void
    {
        VisitEvent::factory()->create([
            'source' => $source,
            'occurred_at' => CarbonImmutable::parse($occurredAt),
        ]);
    }
}
