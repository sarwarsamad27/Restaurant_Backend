<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Pizza', 'description' => 'Delicious pizzas with various toppings', 'sort_order' => 1],
            ['name' => 'Burgers', 'description' => 'Juicy burgers and sandwiches', 'sort_order' => 2],
            ['name' => 'Pasta', 'description' => 'Italian pasta dishes', 'sort_order' => 3],
            ['name' => 'Sushi', 'description' => 'Fresh Japanese sushi and rolls', 'sort_order' => 4],
            ['name' => 'Salads', 'description' => 'Fresh and healthy salads', 'sort_order' => 5],
            ['name' => 'Desserts', 'description' => 'Sweet treats and desserts', 'sort_order' => 6],
            ['name' => 'Beverages', 'description' => 'Drinks and beverages', 'sort_order' => 7],
            ['name' => 'Asian', 'description' => 'Asian cuisine', 'sort_order' => 8],
            ['name' => 'Mexican', 'description' => 'Mexican food', 'sort_order' => 9],
            ['name' => 'Indian', 'description' => 'Indian cuisine', 'sort_order' => 10],
            ['name' => 'Seafood', 'description' => 'Fresh seafood dishes', 'sort_order' => 11],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
