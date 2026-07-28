<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicLandingPageTest extends TestCase
{
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
}
