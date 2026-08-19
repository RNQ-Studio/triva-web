<?php

namespace Tests\Feature\Api;

use App\Models\VisitEvent;
use App\Support\Enums\VisitSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_client_can_record_an_android_or_web_visit_without_storing_raw_identifier(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-19T05:00:00+00:00'));
        $visitId = (string) Str::uuid();

        $this->postJson('/api/v1/analytics/visits', [
            'visit_id' => $visitId,
            'source' => 'android',
            'app_version' => '1.2.3',
            'app_build' => '45',
        ])
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Visit accepted.')
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.source', 'android')
            ->assertJsonPath('data.recorded_at', '2026-08-19T05:00:00+00:00');

        $visit = VisitEvent::query()->sole();

        $this->assertSame(VisitSource::Android, $visit->source);
        $this->assertSame('1.2.3', $visit->app_version);
        $this->assertSame('45', $visit->app_build);
        $this->assertNotSame($visitId, $visit->visit_key);
        $this->assertSame(64, strlen($visit->visit_key));
    }

    public function test_retried_visit_is_idempotent_and_preserves_original_timestamp(): void
    {
        $visitId = (string) Str::uuid();

        $first = $this->postJson('/api/v1/analytics/visits', [
            'visit_id' => $visitId,
            'source' => 'web',
        ])->assertStatus(202);

        $this->travel(5)->minutes();

        $second = $this->postJson('/api/v1/analytics/visits', [
            'visit_id' => $visitId,
            'source' => 'web',
        ])->assertStatus(202);

        $this->assertDatabaseCount('visit_events', 1);
        $this->assertSame(
            $first->json('data.recorded_at'),
            $second->json('data.recorded_at'),
        );
    }

    public function test_visit_ingestion_rejects_invalid_or_server_owned_fields(): void
    {
        $this->postJson('/api/v1/analytics/visits', [
            'visit_id' => (string) Str::uuid(),
            'source' => 'landing_page',
        ])->assertUnprocessable()->assertJsonValidationErrors(['source']);

        $this->postJson('/api/v1/analytics/visits', [
            'visit_id' => 'not-a-uuid',
            'source' => 'android',
        ])->assertUnprocessable()->assertJsonValidationErrors(['visit_id']);

        $this->postJson('/api/v1/analytics/visits', [
            'visit_id' => (string) Str::uuid(),
            'source' => 'web',
            'app_version' => str_repeat('1', 51),
        ])->assertUnprocessable()->assertJsonValidationErrors(['app_version']);

        $this->postJson('/api/v1/analytics/visits', [
            'visit_id' => (string) Str::uuid(),
            'source' => 'android',
            'occurred_at' => '2020-01-01T00:00:00Z',
        ])->assertUnprocessable()->assertJsonValidationErrors(['occurred_at']);

        $this->assertDatabaseCount('visit_events', 0);
    }

    public function test_visit_ingestion_is_rate_limited_by_a_named_limiter(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson('/api/v1/analytics/visits', [
                'visit_id' => (string) Str::uuid(),
                'source' => 'android',
            ])->assertStatus(202);
        }

        $this->postJson('/api/v1/analytics/visits', [
            'visit_id' => (string) Str::uuid(),
            'source' => 'android',
        ])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'VISIT_RATE_LIMITED');

        $this->assertDatabaseCount('visit_events', 30);
    }
}
