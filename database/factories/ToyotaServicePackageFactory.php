<?php

namespace Database\Factories;

use App\Models\ToyotaServicePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ToyotaServicePackage> */
class ToyotaServicePackageFactory extends Factory
{
    protected $model = ToyotaServicePackage::class;

    public function definition(): array
    {
        return [
            'code' => 'berkala-10k',
            'name' => 'Servis berkala 10.000 km',
            'description' => 'Ganti oli mesin, filter oli, dan pemeriksaan menyeluruh.',
            'vehicle_model' => null,
            'km_interval' => 10000,
            'parts_cost' => 650000,
            'labor_cost' => 350000,
            'includes' => ['Oli mesin', 'Filter oli', 'Pemeriksaan 20 titik'],
            'duration_min_minutes' => 60,
            'duration_max_minutes' => 120,
            'is_active' => true,
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to' => null,
            'source_reference' => 'Paket reguler Auto2000 Kertajaya.',
        ];
    }
}
