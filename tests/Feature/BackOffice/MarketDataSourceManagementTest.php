<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\MarketDataSources\MarketDataSourceResource;
use App\Models\MarketDataSource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketDataSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_monitor_provider_but_only_super_admin_can_edit_it(): void
    {
        $source = MarketDataSource::query()
            ->where('code', 'olx_approved_html')
            ->firstOrFail();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(MarketDataSourceResource::getUrl('index'))
            ->assertOk()
            ->assertSee($source->name);
        $this->actingAs($admin)
            ->get(MarketDataSourceResource::getUrl('edit', ['record' => $source]))
            ->assertForbidden();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        $this->actingAs($superAdmin)
            ->get(MarketDataSourceResource::getUrl('edit', ['record' => $source]))
            ->assertOk()
            ->assertSee('Governance izin');
    }
}
