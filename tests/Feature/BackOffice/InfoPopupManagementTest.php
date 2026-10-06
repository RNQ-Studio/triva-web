<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\InfoPopups\InfoPopupResource;
use App\Models\InfoPopup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfoPopupManagementTest extends TestCase
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
        $record = InfoPopup::factory()->create();

        $this->actingAs($admin)
            ->get(InfoPopupResource::getUrl('index'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(InfoPopupResource::getUrl('create'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(InfoPopupResource::getUrl('edit', ['record' => $record]))
            ->assertOk();
    }

    public function test_user_without_permission_cannot_access_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(InfoPopupResource::getUrl('index'))
            ->assertForbidden();
    }
}
