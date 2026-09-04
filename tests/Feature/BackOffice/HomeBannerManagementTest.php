<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\HomeBanners\HomeBannerResource;
use App\Models\HomeBanner;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeBannerManagementTest extends TestCase
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
        $record = HomeBanner::factory()->create();

        $this->actingAs($admin)
            ->get(HomeBannerResource::getUrl('index'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(HomeBannerResource::getUrl('create'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(HomeBannerResource::getUrl('edit', ['record' => $record]))
            ->assertOk();
    }

    public function test_user_without_permission_cannot_access_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(HomeBannerResource::getUrl('index'))
            ->assertForbidden();
    }
}
