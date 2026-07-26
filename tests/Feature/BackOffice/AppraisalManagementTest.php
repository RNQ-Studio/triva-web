<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\Appraisals\AppraisalResource;
use App\Models\Appraisal;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppraisalManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_staff_can_open_appraisal_queue_and_detail(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $appraisal = Appraisal::factory()->create();

        $this->actingAs($staff)
            ->get(AppraisalResource::getUrl('index'))
            ->assertOk()
            ->assertSee($appraisal->reference_no);

        $this->actingAs($staff)
            ->get(AppraisalResource::getUrl('view', ['record' => $appraisal]))
            ->assertOk()
            ->assertSee($appraisal->reference_no);
    }

    public function test_customer_cannot_access_appraisal_back_office(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(AppraisalResource::getUrl('index'))
            ->assertForbidden();
    }
}
