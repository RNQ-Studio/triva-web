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
            CategorySeeder::class,
            AppConfigSeeder::class,
            VehicleMakeSeeder::class,
        ]);

        // Region data (249,036 records) is opt-in to avoid slow default seeds.
        if (filter_var(env('SEED_REGIONS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(RegionSeeder::class);
        }
    }
}
