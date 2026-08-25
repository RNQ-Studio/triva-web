<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Support\Enums\PromotionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Promotion> */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'category' => PromotionCategory::Sales,
            'title' => 'Promo servis berkala',
            'subtitle' => 'Hemat sampai 20%',
            'description' => 'Berlaku untuk seluruh unit Toyota di Auto2000 Kertajaya.',
            'image_path' => null,
            'cta_label' => 'Lihat promo',
            'cta_url' => 'https://auto2000.co.id/promo',
            'sort_order' => 0,
            'is_active' => true,
            'show_as_popup' => false,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => now()->endOfMonth()->toDateString(),
        ];
    }
}
