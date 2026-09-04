<?php

namespace Database\Factories;

use App\Models\HomeBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomeBanner> */
class HomeBannerFactory extends Factory
{
    protected $model = HomeBanner::class;

    public function definition(): array
    {
        return [
            'title' => 'Banner promo cabang',
            'image_path' => 'home-banners/contoh.jpg',
            'link_url' => null,
            'sort_order' => 0,
            'is_active' => true,
            'starts_on' => null,
            'ends_on' => null,
        ];
    }
}
