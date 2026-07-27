<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_privacy_policy_is_publicly_accessible(): void
    {
        $response = $this->get(route('privacy-policy'));

        $response
            ->assertOk()
            ->assertSee('Kebijakan Privasi TRIVA')
            ->assertSee('RNQ Studio')
            ->assertSee('Data yang dikumpulkan')
            ->assertSee('Penghapusan akun')
            ->assertSee('ramadhanrp.developer@gmail.com');
    }

    public function test_short_privacy_url_redirects_to_the_policy(): void
    {
        $this->get('/privacy')
            ->assertRedirect('/privacy-policy')
            ->assertStatus(301);
    }

    public function test_account_deletion_url_points_to_the_prominent_policy_section(): void
    {
        $this->get(route('account-deletion'))
            ->assertRedirect('/privacy-policy#penghapusan-akun');
    }
}
