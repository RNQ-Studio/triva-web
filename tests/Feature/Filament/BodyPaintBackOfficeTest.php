<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BodyPaintEstimates\BodyPaintEstimateResource;
use App\Filament\Resources\BodyPaintEstimates\Pages\ViewBodyPaintEstimate;
use App\Filament\Resources\BodyPaintPriceItems\BodyPaintPriceItemResource;
use App\Models\BodyPaintEstimate;
use App\Models\BodyPaintPriceItem;
use App\Models\User;
use App\Support\Enums\BodyPaintEstimateStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BodyPaintBackOfficeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_queue_detail_and_price_matrix(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $estimate = BodyPaintEstimate::factory()->create([
            'reference_no' => 'BPE-ADMIN-QUEUE',
            'status' => BodyPaintEstimateStatus::Submitted,
            'submitted_at' => now(),
            'due_at' => now()->addHour(),
        ]);
        BodyPaintPriceItem::factory()->create([
            'matrix_code' => 'BP-KERTAJAYA',
            'item_code' => 'BUMPER-PAINT',
        ]);

        $this->actingAs($admin)
            ->get(BodyPaintEstimateResource::getUrl('index'))
            ->assertOk()
            ->assertSee('BPE-ADMIN-QUEUE');
        $this->actingAs($admin)
            ->get(BodyPaintEstimateResource::getUrl('view', [
                'record' => $estimate,
            ]))
            ->assertOk()
            ->assertSee('BPE-ADMIN-QUEUE');
        $this->actingAs($admin)
            ->get(BodyPaintPriceItemResource::getUrl('index'))
            ->assertOk()
            ->assertSee('BP-KERTAJAYA')
            ->assertSee('BUMPER-PAINT');
    }

    public function test_staff_queue_is_assignment_scoped_and_can_start_review(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $assigned = BodyPaintEstimate::factory()->create([
            'reference_no' => 'BPE-ASSIGNED',
            'status' => BodyPaintEstimateStatus::Submitted,
            'assigned_estimator_id' => $staff->getKey(),
            'submitted_at' => now(),
            'due_at' => now()->addHour(),
        ]);
        BodyPaintEstimate::factory()->create([
            'reference_no' => 'BPE-OTHER-ESTIMATOR',
            'status' => BodyPaintEstimateStatus::Submitted,
        ]);

        $this->actingAs($staff)
            ->get(BodyPaintEstimateResource::getUrl('index'))
            ->assertOk()
            ->assertSee('BPE-ASSIGNED')
            ->assertDontSee('BPE-OTHER-ESTIMATOR');

        $this->actingAs($staff);
        Livewire::test(ViewBodyPaintEstimate::class, [
            'record' => $assigned->getRouteKey(),
        ])
            ->callAction('start_review')
            ->assertNotified();

        $this->assertDatabaseHas('body_paint_estimates', [
            'id' => $assigned->getKey(),
            'status' => BodyPaintEstimateStatus::UnderEstimatorReview->value,
            'assigned_estimator_id' => $staff->getKey(),
        ]);
    }
}
