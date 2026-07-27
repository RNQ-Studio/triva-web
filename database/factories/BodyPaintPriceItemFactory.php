<?php

namespace Database\Factories;

use App\Models\BodyPaintPriceItem;
use App\Support\Enums\BodyPaintSeverity;
use App\Support\Enums\BodyPaintWorkType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BodyPaintPriceItem> */
class BodyPaintPriceItemFactory extends Factory
{
    protected $model = BodyPaintPriceItem::class;

    public function definition(): array
    {
        return [
            'matrix_code' => 'BP-TEST',
            'item_code' => strtoupper(Str::random(10)),
            'version' => 1,
            'panel_code' => 'front_bumper',
            'damage_type' => 'scratch',
            'severity' => BodyPaintSeverity::Light,
            'work_type' => BodyPaintWorkType::Repair,
            'labor_low' => 500000,
            'labor_high' => 750000,
            'material_low' => 250000,
            'material_high' => 400000,
            'parts_low' => 0,
            'parts_high' => 0,
            'other_low' => 0,
            'other_high' => 0,
            'duration_min_hours' => 4,
            'duration_max_hours' => 8,
            'is_high_risk' => false,
            'is_active' => true,
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(),
            'source_reference' => 'Price matrix fixture approved for automated tests.',
            'approved_at' => now(),
        ];
    }
}
