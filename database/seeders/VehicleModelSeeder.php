<?php

namespace Database\Seeders;

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class VehicleModelSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, array{source_url: string, models: list<string>}> $catalog */
        $catalog = require __DIR__.'/data/vehicle_models.php';
        $checkedAt = '2026-07-27';

        foreach ($catalog as $makeSlug => $data) {
            $make = VehicleMake::query()->where('slug', $makeSlug)->first();

            if ($make === null) {
                throw new RuntimeException(
                    "Vehicle make [{$makeSlug}] must be seeded before its models.",
                );
            }

            foreach ($data['models'] as $index => $name) {
                VehicleModel::query()->updateOrCreate(
                    [
                        'vehicle_make_id' => $make->getKey(),
                        'slug' => Str::slug($name),
                    ],
                    [
                        'name' => $name,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                        'source_url' => $data['source_url'],
                        'source_checked_at' => $checkedAt,
                    ],
                );
            }
        }
    }
}
