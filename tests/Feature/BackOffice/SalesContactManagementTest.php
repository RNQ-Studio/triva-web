<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\SalesContacts\SalesContactResource;
use App\Models\SalesContact;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesContactManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_management_pages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $record = SalesContact::factory()->create();

        $this->actingAs($admin)
            ->get(SalesContactResource::getUrl('index'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(SalesContactResource::getUrl('create'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(SalesContactResource::getUrl('edit', ['record' => $record]))
            ->assertOk();
    }

    public function test_user_without_permission_cannot_access_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(SalesContactResource::getUrl('index'))
            ->assertForbidden();
    }
}
