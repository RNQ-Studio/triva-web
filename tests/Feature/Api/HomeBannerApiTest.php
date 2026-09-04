<?php

namespace Tests\Feature\Api;

use App\Models\HomeBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomeBannerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-04 03:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_only_running_banners_are_listed_in_order(): void
    {
        HomeBanner::factory()->create(['title' => 'Kedua', 'sort_order' => 2]);
        HomeBanner::factory()->create([
            'title' => 'Pertama',
            'sort_order' => 1,
            'image_path' => 'home-banners/pertama.jpg',
            'link_url' => 'https://auto2000.co.id/promo',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
        ]);
        HomeBanner::factory()->create(['title' => 'Belum tayang', 'starts_on' => '2026-10-01']);
        HomeBanner::factory()->create(['title' => 'Sudah lewat', 'ends_on' => '2026-08-31']);
        HomeBanner::factory()->create(['title' => 'Nonaktif', 'is_active' => false]);

        $response = $this->getJson('/api/v1/banners')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Pertama')
            ->assertJsonPath('data.0.link_url', 'https://auto2000.co.id/promo')
            ->assertJsonPath('data.1.title', 'Kedua');

        self::assertStringEndsWith(
            '/storage/home-banners/pertama.jpg',
            (string) $response->json('data.0.image_url'),
        );
    }
}
