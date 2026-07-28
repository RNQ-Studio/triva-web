<?php

namespace Database\Seeders;

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class VehicleVariantSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * @var array<string, array<string, array{
         *     source_url: string,
         *     variants: list<array{
         *         name: string,
         *         year_from: int,
         *         year_to?: int,
         *         transmission: string,
         *         fuel_type: string,
         *         aliases?: list<string>,
         *         source_url?: string
         *     }>
         * }>> $catalog
         */
        $catalog = require __DIR__.'/data/vehicle_variants.php';
        $checkedAt = '2026-07-28';

        foreach ($catalog as $makeSlug => $models) {
            $make = VehicleMake::query()->where('slug', $makeSlug)->first();

            if ($make === null) {
                throw new RuntimeException(
                    "Vehicle make [{$makeSlug}] must be seeded before its variants.",
                );
            }

            foreach ($models as $modelName => $data) {
                $model = VehicleModel::query()
                    ->where('vehicle_make_id', $make->getKey())
                    ->where('name', $modelName)
                    ->first();

                if ($model === null) {
                    throw new RuntimeException(
                        "Vehicle model [{$makeSlug}/{$modelName}] must be seeded before its variants.",
                    );
                }

                foreach ($data['variants'] as $index => $variant) {
                    VehicleVariant::query()->updateOrCreate(
                        [
                            'vehicle_model_id' => $model->getKey(),
                            'slug' => Str::slug($variant['name']),
                            'year_from' => $variant['year_from'],
                        ],
                        [
                            'name' => $variant['name'],
                            'year_to' => $variant['year_to'] ?? null,
                            'transmission' => $variant['transmission'],
                            'fuel_type' => $variant['fuel_type'],
                            'aliases' => $variant['aliases'] ?? null,
                            'sort_order' => $index + 1,
                            'is_active' => true,
                            'source_url' => $variant['source_url'] ?? $data['source_url'],
                            'source_checked_at' => $checkedAt,
                        ],
                    );
                }
            }
        }
    }
}
