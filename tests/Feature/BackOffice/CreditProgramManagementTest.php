<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\CreditFollowUpLeads\CreditFollowUpLeadResource;
use App\Filament\Resources\CreditPrograms\CreditProgramResource;
use App\Filament\Resources\CreditSimulations\CreditSimulationResource;
use App\Models\CreditFollowUpLead;
use App\Models\CreditProgram;
use App\Models\CreditSimulation;
use App\Models\User;
use App\Services\CreditProgramCsvImportService;
use App\Support\Enums\CreditLeadStatus;
use App\Support\Enums\CreditProgramStatus;
use Database\Seeders\CreditProgramDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        CreditProgram::query()->delete();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_open_credit_program_and_lead_resources(): void
    {
        $this->actingAs($this->admin)
            ->get(CreditProgramResource::getUrl('index'))
            ->assertOk();
        $this->actingAs($this->admin)
            ->get(CreditFollowUpLeadResource::getUrl('index'))
            ->assertOk();
        $simulation = CreditSimulation::factory()->create();
        $this->actingAs($this->admin)
            ->get(CreditSimulationResource::getUrl('index'))
            ->assertOk();
        $this->actingAs($this->admin)
            ->get(CreditSimulationResource::getUrl('view', [
                'record' => $simulation,
            ]))
            ->assertOk()
            ->assertSee($simulation->reference_no);
    }

    public function test_csv_preview_and_import_group_tenors_and_record_approval(): void
    {
        $path = $this->writeCsv([
            $this->validRow(['tenor_months' => '36']),
            $this->validRow([
                'tenor_months' => '60',
                'annual_flat_rate_basis_points' => '525',
            ]),
        ]);

        try {
            $service = app(CreditProgramCsvImportService::class);
            $preview = $service->preview($path);
            $this->assertSame([], $preview['errors']);
            $this->assertSame(1, $preview['program_count']);
            $this->assertSame(2, $preview['tenor_count']);

            $result = $service->import($path, $this->admin);
            $this->assertSame(1, $result['program_count']);
            $program = CreditProgram::query()->firstOrFail();
            $this->assertCount(2, $program->tenor_options);
            $this->assertSame($this->admin->getKey(), $program->approved_by);
            $this->assertNotNull($program->approved_at);
            $this->assertDatabaseHas('activity_log', [
                'subject_type' => CreditProgram::class,
                'subject_id' => $program->getKey(),
                'event' => 'created',
            ]);
        } finally {
            unlink($path);
        }
    }

    public function test_demo_program_seeder_is_idempotent_and_does_not_reactivate_retired_data(): void
    {
        $this->seed(CreditProgramDemoSeeder::class);
        $this->seed(CreditProgramDemoSeeder::class);

        $program = CreditProgram::query()
            ->where('program_code', 'TRIVA-DEMO-CREDIT')
            ->firstOrFail();
        $this->assertDatabaseCount('credit_programs', 1);
        $this->assertTrue($program->is_demo);
        $this->assertStringContainsString(
            'Bukan Penawaran Kredit',
            $program->program_name,
        );
        $this->assertStringContainsString('dummy', $program->source_reference);
        $this->assertCount(3, $program->tenor_options);
        $this->assertSame(
            '2026-08-31',
            $program->effective_to?->toDateString(),
        );

        $program->update(['status' => CreditProgramStatus::Inactive]);
        $this->seed(CreditProgramDemoSeeder::class);

        $this->assertSame(
            CreditProgramStatus::Inactive,
            $program->refresh()->status,
        );
    }

    public function test_csv_preview_reports_row_errors_without_writing(): void
    {
        $path = $this->writeCsv([
            $this->validRow([
                'otr_price' => '0',
                'maximum_dp_basis_points' => '10001',
                'effective_to' => '2026-07-01',
            ]),
        ]);

        try {
            $preview = app(CreditProgramCsvImportService::class)
                ->preview($path);
            $this->assertNotEmpty($preview['errors']);
            $this->assertSame(0, $preview['program_count']);
            $this->assertDatabaseCount('credit_programs', 0);
        } finally {
            unlink($path);
        }
    }

    public function test_used_program_is_immutable_and_must_be_versioned(): void
    {
        $program = CreditProgram::factory()->create();
        CreditSimulation::factory()->create([
            'credit_program_id' => $program->getKey(),
        ]);

        $this->expectException(ValidationException::class);
        $program->update(['otr_price' => 330000000]);
    }

    public function test_staff_lead_query_is_scoped_to_assignment(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $ownSimulation = CreditSimulation::factory()->create();
        $otherSimulation = CreditSimulation::factory()->create();
        $own = $this->createLead($ownSimulation, $staff);
        $this->createLead($otherSimulation, null);

        $this->actingAs($staff);
        $this->assertSame(
            [$own->getKey()],
            CreditFollowUpLeadResource::getEloquentQuery()
                ->pluck('id')
                ->all(),
        );
        $this->assertSame(
            [$ownSimulation->getKey()],
            CreditSimulationResource::getEloquentQuery()
                ->pluck('id')
                ->all(),
        );
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'credit-program-');
        $this->assertIsString($path);
        $handle = fopen($path, 'wb');
        $this->assertNotFalse($handle);
        fputcsv($handle, array_keys($this->validRow()));
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }
        fclose($handle);

        return $path;
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validRow(array $overrides = []): array
    {
        return [
            'program_code' => 'CSV-TEST',
            'version' => '1',
            'partner_name' => 'Partner Test',
            'program_name' => 'Program CSV Test',
            'city' => 'Surabaya',
            'vehicle_model' => 'Avanza',
            'vehicle_variant' => '1.5 G CVT',
            'model_year' => '2026',
            'otr_price' => '320000000',
            'approved_discount' => '10000000',
            'minimum_dp_basis_points' => '2000',
            'maximum_dp_basis_points' => '8000',
            'tenor_months' => '36',
            'annual_flat_rate_basis_points' => '450',
            'administration_fee' => '2500000',
            'provision_fee' => '1000000',
            'upfront_insurance' => '6000000',
            'other_upfront_cost_label' => 'Fidusia',
            'other_upfront_costs' => '500000',
            'effective_from' => '2026-07-01',
            'effective_to' => '2026-08-31',
            'source_reference' => 'Dokumen program test.',
            'status' => 'approved',
            ...$overrides,
        ];
    }

    private function createLead(
        CreditSimulation $simulation,
        ?User $sales,
    ): CreditFollowUpLead {
        return CreditFollowUpLead::query()->create([
            'reference_no' => 'SKL-TEST-'.Str::random(8),
            'simulation_id' => $simulation->getKey(),
            'user_id' => $simulation->user_id,
            'assigned_sales_id' => $sales?->getKey(),
            'status' => CreditLeadStatus::New,
            'contact_channel' => 'whatsapp',
            'consent_version' => 'credit-follow-up-v1',
            'consent_at' => now(),
        ]);
    }
}
