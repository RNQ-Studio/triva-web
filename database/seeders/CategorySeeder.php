<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        if (Category::query()->exists()) {
            return;
        }

        Category::query()->insert([
            ['name' => 'General', 'slug' => 'general', 'description' => 'General content', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'News', 'slug' => 'news', 'description' => 'News and updates', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tips', 'slug' => 'tips', 'description' => 'Helpful tips', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Lifestyle', 'slug' => 'lifestyle', 'description' => 'Lifestyle content', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Inspiration', 'slug' => 'inspiration', 'description' => 'Inspiration and stories', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
