<?php

namespace Database\Factories;

use App\Models\InfoPopup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InfoPopup> */
class InfoPopupFactory extends Factory
{
    protected $model = InfoPopup::class;

    public function definition(): array
    {
        return [
            'title' => 'Info servis cabang',
            'image_path' => 'info-popups/contoh.jpg',
            'button_label' => null,
            'button_url' => null,
            'sort_order' => 0,
            'interval_hours' => 24,
            'is_active' => true,
            'starts_on' => null,
            'ends_on' => null,
        ];
    }
}
