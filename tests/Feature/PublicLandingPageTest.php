<?php

namespace Tests\Feature;

use App\Services\VisitTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PublicLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_page_presents_the_triva_brand_and_product_ui(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Mobil Anda,', false)
            ->assertSee('Buka TRIVA')
            ->assertSee('landing/triva-logo.png', false)
            ->assertSee('landing/app-home.webp', false)
            ->assertSee('landing/appraisal-result.webp', false)
            ->assertSee('landing/booking-status.webp', false)
            ->assertSee('apple-touch-icon.png', false);
    }

    public function test_public_landing_page_does_not_expose_admin_panel_navigation(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('href="/admin"', false)
            ->assertDontSee('Admin Panel');
    }

    public function test_landing_page_assets_are_available_from_public_path(): void
    {
        foreach ([
            'landing/triva-logo.png',
            'landing/triva-service-bay.webp',
            'landing/triva-service-bay.jpg',
            'landing/app-home.webp',
            'landing/appraisal-result.webp',
            'landing/booking-status.webp',
            'apple-touch-icon.png',
            'apple-touch-icon-precomposed.png',
        ] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }

    public function test_landing_page_records_only_one_visit_for_the_same_session(): void
    {
        $this->get('/')->assertOk();
        $this->get('/')->assertOk();

        $this->assertDatabaseCount('visit_events', 1);
        $this->assertDatabaseHas('visit_events', ['source' => 'landing_page']);
    }

    public function test_landing_page_skips_head_requests_and_known_bots(): void
    {
        $this->head('/')->assertOk();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/')->assertOk();

        $this->assertDatabaseCount('visit_events', 0);
    }

    public function test_landing_page_skips_purpose_prefetch_requests(): void
    {
        $this->withHeaders([
            'Purpose' => 'prefetch',
            'User-Agent' => 'Mozilla/5.0',
        ])->get('/')->assertOk();

        $this->assertDatabaseCount('visit_events', 0);
    }

    public function test_landing_page_skips_sec_purpose_prefetch_requests(): void
    {
        $this->withHeaders([
            'Sec-Purpose' => 'prefetch',
            'User-Agent' => 'Mozilla/5.0',
        ])->get('/')->assertOk();

        $this->assertDatabaseCount('visit_events', 0);
    }

    public function test_landing_page_skips_curl_and_wget_automation(): void
    {
        $this->withHeader('User-Agent', 'curl/8.15.0')->get('/')->assertOk();
        $this->withHeader('User-Agent', 'Wget/1.25.0')->get('/')->assertOk();

        $this->assertDatabaseCount('visit_events', 0);
    }

    public function test_landing_page_remains_available_when_visit_tracking_fails(): void
    {
        $this->mock(
            VisitTrackingService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('record')
                    ->once()
                    ->andThrow(new RuntimeException('Telemetry unavailable.'));
            },
        );

        $this->get('/')
            ->assertOk()
            ->assertSee('Mobil Anda,', false);
    }
}
