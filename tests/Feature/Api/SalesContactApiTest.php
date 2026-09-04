<?php

namespace Tests\Feature\Api;

use App\Models\SalesContact;
use App\Support\Enums\SalesContactRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesContactApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_contacts_are_listed_with_supervisors_first(): void
    {
        SalesContact::factory()->create([
            'name' => 'Budi Sales',
            'role' => SalesContactRole::Sales,
            'sort_order' => 1,
            'whatsapp_number' => '0812-3456-7890',
        ]);
        SalesContact::factory()->spv()->create([
            'name' => 'Sari SPV',
            'sort_order' => 5,
            'whatsapp_number' => '+62 813 0000 1111',
        ]);
        SalesContact::factory()->create([
            'name' => 'Nonaktif',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/sales-contacts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Sari SPV')
            ->assertJsonPath('data.0.role', 'spv')
            ->assertJsonPath('data.0.role_label', 'Supervisor (SPV)')
            ->assertJsonPath('data.0.whatsapp_number', '6281300001111')
            ->assertJsonPath('data.0.photo_url', null)
            ->assertJsonPath('data.1.name', 'Budi Sales')
            ->assertJsonPath('data.1.whatsapp_number', '6281234567890')
            ->assertJsonMissing(['name' => 'Nonaktif']);
    }

    public function test_photo_url_is_served_from_the_public_disk(): void
    {
        SalesContact::factory()->create(['photo_path' => 'sales-contacts/budi.jpg']);

        $response = $this->getJson('/api/v1/sales-contacts')->assertOk();

        self::assertStringEndsWith(
            '/storage/sales-contacts/budi.jpg',
            (string) $response->json('data.0.photo_url'),
        );
    }
}
