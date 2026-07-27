<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminUserApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Admin Utama']);
        $this->admin->assignRole('admin');
        $this->customer = User::factory()->create([
            'name' => 'Pelanggan Existing',
            'email' => 'existing@example.com',
        ]);
    }

    public function test_admin_can_search_existing_users_with_pagination(): void
    {
        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/users?search=existing&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->customer->getKey())
            ->assertJsonPath('data.0.email', 'existing@example.com')
            ->assertJsonPath('data.0.is_admin', false)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 10);

        $this->getJson('/api/v1/admin/users?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_admin_can_grant_admin_access_idempotently(): void
    {
        Passport::actingAs($this->admin);
        $path = "/api/v1/admin/users/{$this->customer->getKey()}/grant-admin";

        $this->postJson($path)
            ->assertOk()
            ->assertJsonPath('data.id', $this->customer->getKey())
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonPath('data.roles.0', 'admin');

        $this->assertTrue($this->customer->refresh()->hasRole('admin'));
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'access-management',
            'event' => null,
            'subject_type' => User::class,
            'subject_id' => $this->customer->getKey(),
            'causer_type' => User::class,
            'causer_id' => $this->admin->getKey(),
            'description' => 'Admin access granted',
        ]);

        $this->postJson($path)
            ->assertOk()
            ->assertJsonPath('data.is_admin', true);

        $this->assertDatabaseCount('model_has_roles', 2);
        $this->assertSame(
            1,
            Activity::query()
                ->where('log_name', 'access-management')
                ->where('subject_id', $this->customer->getKey())
                ->count(),
        );
    }

    public function test_user_management_routes_enforce_auth_permission_and_lookup(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
        $this->postJson(
            "/api/v1/admin/users/{$this->customer->getKey()}/grant-admin"
        )->assertUnauthorized();

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Passport::actingAs($staff);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->postJson(
            "/api/v1/admin/users/{$this->customer->getKey()}/grant-admin"
        )->assertForbidden();

        Passport::actingAs($this->admin);
        $this->postJson('/api/v1/admin/users/999999/grant-admin')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_inactive_user_cannot_receive_admin_access(): void
    {
        $this->customer->update(['is_active' => false]);
        Passport::actingAs($this->admin);

        $this->postJson(
            "/api/v1/admin/users/{$this->customer->getKey()}/grant-admin"
        )
            ->assertConflict()
            ->assertJsonPath('code', 'ADMIN_USER_INACTIVE');

        $this->assertFalse($this->customer->refresh()->hasRole('admin'));
    }
}
