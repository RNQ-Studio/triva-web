<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            DeveloperAdminSeeder::class,
            CategorySeeder::class,
            AppConfigSeeder::class,
            VehicleMakeSeeder::class,
            VehicleModelSeeder::class,
            VehicleVariantSeeder::class,
            ToyotaServiceMasterSeeder::class,
            OtoxpertMasterSeeder::class,
        ]);

        // Region data (249,036 records) is opt-in to avoid slow default seeds.
        if (config('app.seed_regions', false)) {
            $this->call(RegionSeeder::class);
        }
    }
}
