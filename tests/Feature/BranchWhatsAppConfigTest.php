<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchWhatsAppConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_config_exposes_the_branch_whatsapp_numbers(): void
    {
        $this->getJson('/api/v1/app/config')
            ->assertOk()
            // Nomor pada notulensi 19 Agustus 2026.
            ->assertJsonPath('data.whatsapp_toyota_service', '6285713112000')
            ->assertJsonPath('data.whatsapp_otoxpert', '6281511060290')
            ->assertJsonPath('data.whatsapp_body_paint', '6285713112000');
    }
}
