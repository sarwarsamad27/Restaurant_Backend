<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class MenuCategorySeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('name', 'Taco Fiesta')->first();

        if (!$restaurant) {
            return;
        }

        $categories = collect([
            'Appetizers',
            'Tacos',
            'Burritos',
            'Bowls',
            'Sides',
            'Desserts',
            'Drinks',
        ])->map(fn ($name, $index) => [
            'name' => $name,
            'description' => $name,
            'sort_order' => $index,
        ]);

        $categories->each(function ($attributes) use ($restaurant) {
            Category::firstOrCreate(
                ['name' => $attributes['name']],
                $attributes
            );
        });
    }
}
