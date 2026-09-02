<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_maintenance_page(): void
    {
        config([
            'maintenance.enabled' => true,
            'maintenance.title' => 'Sistem Sedang Dalam Perawatan',
            'maintenance.message' => 'TRIVA akan kembali sebentar lagi.',
        ]);

        $response = $this->get('/')->assertStatus(503);

        $response->assertViewIs('maintenance');
        $response->assertSee('Sistem Sedang Dalam Perawatan');
        $response->assertSee('TRIVA akan kembali sebentar lagi.');
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_public_content_pages_are_covered_too(): void
    {
        config(['maintenance.enabled' => true]);

        $this->get('/articles')->assertStatus(503);
        $this->get('/privacy-policy')->assertStatus(503);
    }

    public function test_page_shows_estimated_return_time_when_announced(): void
    {
        config([
            'maintenance.enabled' => true,
            'maintenance.until' => '2026-09-02T17:00:00+07:00',
        ]);

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Diperkirakan kembali normal pada')
            ->assertSee('17:00 WIB', false);
    }

    public function test_page_omits_return_time_when_not_announced(): void
    {
        config(['maintenance.enabled' => true, 'maintenance.until' => null]);

        $this->get('/')
            ->assertStatus(503)
            ->assertDontSee('Diperkirakan kembali normal pada');
    }

    public function test_page_is_not_indexed_by_search_engines(): void
    {
        config(['maintenance.enabled' => true]);

        $this->get('/')->assertSee('noindex, nofollow', false);
    }

    /**
     * Back-office harus tetap hidup supaya tim ops bisa memeriksa keadaan dan
     * mematikan sakelarnya tanpa akses shell.
     */
    public function test_back_office_stays_reachable_by_default(): void
    {
        config(['maintenance.enabled' => true]);

        $this->get('/admin/login')->assertStatus(200);
    }

    public function test_back_office_can_be_locked_down_explicitly(): void
    {
        config(['maintenance.enabled' => true, 'maintenance.allow_admin' => false]);

        $this->get('/admin/login')->assertStatus(503);
    }

    public function test_web_is_reachable_when_switch_is_off(): void
    {
        config(['maintenance.enabled' => false]);

        $this->get('/')->assertOk();
    }
}
