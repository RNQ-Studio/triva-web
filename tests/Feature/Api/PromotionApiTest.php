<?php

namespace Tests\Feature\Api;

use App\Models\Promotion;
use App\Support\Enums\PromotionCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 03:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_only_running_promotions_are_listed_in_order(): void
    {
        Promotion::factory()->create([
            'title' => 'Promo OtoXpert Agustus',
            'category' => PromotionCategory::Otoxpert,
            'sort_order' => 2,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-31',
        ]);
        Promotion::factory()->create([
            'title' => 'Promo Sales Agustus',
            'category' => PromotionCategory::Sales,
            'sort_order' => 1,
            'show_as_popup' => true,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-31',
        ]);
        // Promo bulan depan sudah disiapkan cabang tetapi belum boleh tayang.
        Promotion::factory()->create([
            'title' => 'Promo September',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
        ]);
        Promotion::factory()->create([
            'title' => 'Promo Juli',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
        ]);
        Promotion::factory()->create([
            'title' => 'Promo dinonaktifkan',
            'is_active' => false,
            'starts_on' => '2026-08-01',
        ]);

        $response = $this->getJson('/api/v1/promotions')->assertOk();
        $titles = array_column($response->json('data'), 'title');

        self::assertSame(
            ['Promo Sales Agustus', 'Promo OtoXpert Agustus'],
            $titles,
        );
        $response
            ->assertJsonPath('data.0.category', 'sales')
            ->assertJsonPath('data.0.category_label', 'Sales')
            ->assertJsonPath('data.0.show_as_popup', true)
            ->assertJsonPath('data.1.show_as_popup', false);
    }

    public function test_the_promotion_list_is_readable_without_signing_in(): void
    {
        Promotion::factory()->create(['starts_on' => '2026-08-01']);

        $this->getJson('/api/v1/promotions')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
