<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ToyotaServiceBookings\Pages\ListToyotaServiceBookings;
use App\Filament\Resources\ToyotaServiceBookings\Pages\ViewToyotaServiceBooking;
use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DeveloperAdminSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ToyotaServiceMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ToyotaServiceBackOfficeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-27 01:00:00', 'UTC'));
        $this->seed([
            RolePermissionSeeder::class,
            ToyotaServiceMasterSeeder::class,
        ]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_render_queue_calendar_detail_and_configuration_pages(): void
    {
        $location = ToyotaServiceLocation::query()
            ->where('code', 'auto2000-kertajaya')
            ->firstOrFail();
        $serviceType = ToyotaServiceType::query()
            ->where('code', 'periodic-service')
            ->firstOrFail();
        $customer = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'user_id' => $customer->getKey(),
            'make' => 'Toyota',
        ]);
        $booking = ToyotaServiceBooking::factory()->create([
            'user_id' => $customer->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'service_location_id' => $location->getKey(),
            'service_type_id' => $serviceType->getKey(),
            'reference_no' => 'BTS-20260729-CALENDAR',
            'active_slot_start_at' => Carbon::parse('2026-07-28 17:30:00', 'UTC'),
            'active_slot_end_at' => Carbon::parse('2026-07-28 19:30:00', 'UTC'),
        ]);

        $this->actingAs($this->admin);
        $this->get('/admin/toyota-service-bookings')->assertOk();
        $this->get('/admin/toyota-service-bookings/schedule?date=2026-07-29')
            ->assertOk()
            ->assertSee('BTS-20260729-CALENDAR')
            ->assertSee('00:30')
            ->assertSee($booking->status->customerLabel());
        $this->get("/admin/toyota-service-bookings/{$booking->getKey()}")
            ->assertOk()
            ->assertSee('BTS-20260729-CALENDAR');
        $this->get('/admin/toyota-service-locations')->assertOk();
        $this->get('/admin/toyota-service-types')->assertOk();
        $this->get('/admin/toyota-service-holidays')->assertOk();
        $this->get('/admin/toyota-ths-coverages')->assertOk();
    }

    public function test_calendar_falls_back_safely_for_malformed_date_and_staff_is_view_only_for_config(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $this->actingAs($staff);

        $this->get('/admin/toyota-service-bookings/schedule?date=not-a-date')
            ->assertOk()
            ->assertSee('Kalender Booking Toyota Service');
        $this->get('/admin/toyota-service-bookings/schedule?date='.str_repeat('9', 5000))
            ->assertOk();
        $this->get('/admin/toyota-ths-coverages')->assertOk();
        $this->get('/admin/toyota-ths-coverages/create')->assertForbidden();
    }

    public function test_daily_csv_export_is_local_date_scoped_and_formula_safe(): void
    {
        $location = ToyotaServiceLocation::query()
            ->where('code', 'auto2000-kertajaya')
            ->firstOrFail();
        $serviceType = ToyotaServiceType::query()
            ->where('code', 'periodic-service')
            ->firstOrFail();
        $customer = User::factory()->create(['name' => " \t=DANGEROUS_FORMULA"]);
        $vehicle = Vehicle::factory()->create([
            'user_id' => $customer->getKey(),
            'make' => 'Toyota',
        ]);
        ToyotaServiceBooking::factory()->create([
            'user_id' => $customer->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'service_location_id' => $location->getKey(),
            'service_type_id' => $serviceType->getKey(),
            'reference_no' => 'BTS-LOCAL-DATE-INCLUDED',
            'active_slot_start_at' => Carbon::parse('2026-07-28 17:30:00', 'UTC'),
            'active_slot_end_at' => Carbon::parse('2026-07-28 19:30:00', 'UTC'),
        ]);
        ToyotaServiceBooking::factory()->create([
            'user_id' => $customer->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'service_location_id' => $location->getKey(),
            'service_type_id' => $serviceType->getKey(),
            'reference_no' => 'BTS-OTHER-DATE-EXCLUDED',
            'active_slot_start_at' => Carbon::parse('2026-07-29 17:30:00', 'UTC'),
            'active_slot_end_at' => Carbon::parse('2026-07-29 19:30:00', 'UTC'),
        ]);
        $this->actingAs($this->admin);

        $component = Livewire::test(ListToyotaServiceBookings::class)
            ->callAction('exportDaily', ['date' => '2026-07-29'])
            ->assertFileDownloaded('toyota-service-bookings-2026-07-29.csv');
        $csv = base64_decode((string) data_get($component->effects, 'download.content'));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('reference_no,status,customer', $csv);
        $this->assertStringContainsString('BTS-LOCAL-DATE-INCLUDED', $csv);
        $this->assertStringContainsString("' \t=DANGEROUS_FORMULA", $csv);
        $this->assertStringNotContainsString('BTS-OTHER-DATE-EXCLUDED', $csv);
    }

    public function test_confirm_action_offers_only_customer_preferences_and_generated_payload_succeeds(): void
    {
        $location = ToyotaServiceLocation::query()
            ->where('code', 'auto2000-kertajaya')
            ->firstOrFail();
        $serviceType = ToyotaServiceType::query()
            ->where('code', 'periodic-service')
            ->firstOrFail();
        $customer = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'user_id' => $customer->getKey(),
            'make' => 'Toyota',
        ]);
        $booking = ToyotaServiceBooking::factory()->create([
            'user_id' => $customer->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'service_location_id' => $location->getKey(),
            'service_type_id' => $serviceType->getKey(),
            'primary_start_at' => Carbon::parse('2026-07-29 02:00:00', 'UTC'),
            'primary_end_at' => Carbon::parse('2026-07-29 04:00:00', 'UTC'),
            'alternative_start_at' => Carbon::parse('2026-07-30 06:00:00', 'UTC'),
            'alternative_end_at' => Carbon::parse('2026-07-30 08:00:00', 'UTC'),
            'active_slot_start_at' => Carbon::parse('2026-07-29 02:00:00', 'UTC'),
            'active_slot_end_at' => Carbon::parse('2026-07-29 04:00:00', 'UTC'),
        ]);
        $this->actingAs($this->admin);

        Livewire::test(ViewToyotaServiceBooking::class, ['record' => $booking->getRouteKey()])
            ->mountAction('confirm')
            ->assertMountedActionModalSee(['2026-07-29', '2026-07-30'])
            ->assertMountedActionModalDontSee('2026-08-01')
            ->unmountAction()
            ->callAction('confirm', [
                'confirmed_date' => '2026-07-29',
                'confirmed_window' => '09:00-11:00',
                'pic_name' => 'Advisor dari UI',
                'arrival_instructions' => 'Datang 15 menit sebelum jadwal.',
            ])
            ->assertNotified();

        $this->assertDatabaseHas('toyota_service_bookings', [
            'id' => $booking->getKey(),
            'status' => 'confirmed',
            'pic_name' => 'Advisor dari UI',
        ]);
    }

    public function test_developer_admin_seeder_grants_requested_account_admin_access(): void
    {
        $this->seed(DeveloperAdminSeeder::class);

        $developer = User::query()
            ->where('email', 'ramadhanrp.developer@gmail.com')
            ->firstOrFail();

        $this->assertTrue($developer->is_active);
        $this->assertTrue($developer->hasRole('admin'));
    }
}
