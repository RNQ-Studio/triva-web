<?php

namespace Database\Factories;

use App\Models\PartnerLogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartnerLogo> */
class PartnerLogoFactory extends Factory
{
    protected $model = PartnerLogo::class;

    public function definition(): array
    {
        return [
            'name' => 'Mitra cabang',
            'logo_path' => 'partner-logos/contoh.png',
            'link_url' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
