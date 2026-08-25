<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // Halaman depan mencatat kunjungan lewat middleware analitik. Tanpa
    // RefreshDatabase, baris kunjungan dari test ini ikut ter-commit dan
    // terbawa ke seluruh sisa suite, membuat hitungan visit_events di test
    // landing page meleset.
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
