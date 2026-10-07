<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Category;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $pizzaRestaurant = Restaurant::where('name', 'The Hungry Spoon')->orWhere('name', 'Pizza Paradise')->first();
        $burgerRestaurant = Restaurant::where('name', 'Burger House')->first();
        $pastaRestaurant = Restaurant::where('name', 'Pasta Palace')->first();

        $pizzaCategory = Category::where('name', 'Pizza')->first();
        $burgerCategory = Category::where('name', 'Burgers')->first();
        $pastaCategory = Category::where('name', 'Pasta')->first();
        $sushiCategory = Category::where('name', 'Sushi')->first();
        $dessertCategory = Category::where('name', 'Desserts')->first();
        $beverageCategory = Category::where('name', 'Beverages')->first();

        // Pizza Paradise Menu
        $pizzaItems = [
            [
                'restaurant_id' => $pizzaRestaurant->id,
                'category_id' => $pizzaCategory->id,
                'name' => 'Margherita Pizza',
                'description' => 'Classic pizza with tomato sauce, mozzarella, and fresh basil',
                'price' => 12.99,
                'discount_price' => 10.99,
                'is_vegetarian' => true,
                'is_available' => true,
                'is_featured' => true,
                'preparation_time' => 20,
                'ingredients' => ['Tomato Sauce', 'Mozzarella', 'Basil', 'Olive Oil'],
                'rating' => 4.5,
                'total_reviews' => 45,
            ],
            [
                'restaurant_id' => $pizzaRestaurant->id,
                'category_id' => $pizzaCategory->id,
                'name' => 'Pepperoni Pizza',
                'description' => 'Loaded with pepperoni and extra cheese',
                'price' => 14.99,
                'is_available' => true,
                'is_featured' => true,
                'preparation_time' => 22,
                'ingredients' => ['Tomato Sauce', 'Mozzarella', 'Pepperoni'],
                'rating' => 4.7,
                'total_reviews' => 67,
            ],
            [
                'restaurant_id' => $pizzaRestaurant->id,
                'category_id' => $pizzaCategory->id,
                'name' => 'Vegetarian Supreme',
                'description' => 'Loaded with fresh vegetables',
                'price' => 13.99,
                'is_vegetarian' => true,
                'is_available' => true,
                'preparation_time' => 25,
                'ingredients' => ['Tomato Sauce', 'Mozzarella', 'Bell Peppers', 'Mushrooms', 'Onions', 'Olives'],
                'rating' => 4.4,
                'total_reviews' => 32,
            ],
        ];

        // Burger House Menu
        $burgerItems = [
            [
                'restaurant_id' => $burgerRestaurant->id,
                'category_id' => $burgerCategory->id,
                'name' => 'Classic Cheeseburger',
                'description' => 'Beef patty with cheese, lettuce, tomato, and special sauce',
                'price' => 9.99,
                'is_available' => true,
                'is_featured' => true,
                'preparation_time' => 15,
                'ingredients' => ['Beef Patty', 'Cheese', 'Lettuce', 'Tomato', 'Onion', 'Special Sauce'],
                'rating' => 4.6,
                'total_reviews' => 89,
            ],
            [
                'restaurant_id' => $burgerRestaurant->id,
                'category_id' => $burgerCategory->id,
                'name' => 'Bacon Deluxe Burger',
                'description' => 'Double beef patty with crispy bacon and BBQ sauce',
                'price' => 12.99,
                'discount_price' => 11.99,
                'is_available' => true,
                'is_featured' => true,
                'preparation_time' => 18,
                'ingredients' => ['Beef Patty', 'Bacon', 'Cheese', 'BBQ Sauce', 'Onion Rings'],
                'rating' => 4.8,
                'total_reviews' => 102,
            ],
            [
                'restaurant_id' => $burgerRestaurant->id,
                'category_id' => $burgerCategory->id,
                'name' => 'Veggie Burger',
                'description' => 'Plant-based patty with fresh vegetables',
                'price' => 10.99,
                'is_vegetarian' => true,
                'is_vegan' => true,
                'is_available' => true,
                'preparation_time' => 15,
                'ingredients' => ['Veggie Patty', 'Lettuce', 'Tomato', 'Avocado', 'Vegan Mayo'],
                'rating' => 4.3,
                'total_reviews' => 54,
            ],
        ];

        // Pasta Palace Menu
        $pastaItems = [
            [
                'restaurant_id' => $pastaRestaurant->id,
                'category_id' => $pastaCategory->id,
                'name' => 'Spaghetti Carbonara',
                'description' => 'Creamy pasta with bacon and parmesan',
                'price' => 15.99,
                'is_available' => true,
                'is_featured' => true,
                'preparation_time' => 20,
                'ingredients' => ['Spaghetti', 'Bacon', 'Eggs', 'Parmesan', 'Black Pepper'],
                'rating' => 4.7,
                'total_reviews' => 78,
            ],
            [
                'restaurant_id' => $pastaRestaurant->id,
                'category_id' => $pastaCategory->id,
                'name' => 'Penne Arrabbiata',
                'description' => 'Spicy tomato sauce with garlic and chili',
                'price' => 13.99,
                'is_vegetarian' => true,
                'is_spicy' => true,
                'is_available' => true,
                'preparation_time' => 18,
                'ingredients' => ['Penne', 'Tomato Sauce', 'Garlic', 'Chili Flakes', 'Olive Oil'],
                'rating' => 4.5,
                'total_reviews' => 61,
            ],
            [
                'restaurant_id' => $pastaRestaurant->id,
                'category_id' => $pastaCategory->id,
                'name' => 'Fettuccine Alfredo',
                'description' => 'Rich and creamy Alfredo sauce',
                'price' => 14.99,
                'is_vegetarian' => true,
                'is_available' => true,
                'preparation_time' => 20,
                'ingredients' => ['Fettuccine', 'Cream', 'Butter', 'Parmesan', 'Garlic'],
                'rating' => 4.6,
                'total_reviews' => 92,
            ],
        ];

        // Desserts for all restaurants
        $desserts = [
            [
                'restaurant_id' => $pizzaRestaurant->id,
                'category_id' => $dessertCategory->id,
                'name' => 'Tiramisu',
                'description' => 'Classic Italian dessert',
                'price' => 6.99,
                'is_vegetarian' => true,
                'is_available' => true,
                'preparation_time' => 5,
                'rating' => 4.8,
                'total_reviews' => 34,
            ],
            [
                'restaurant_id' => $burgerRestaurant->id,
                'category_id' => $dessertCategory->id,
                'name' => 'Chocolate Brownie',
                'description' => 'Warm brownie with ice cream',
                'price' => 5.99,
                'is_vegetarian' => true,
                'is_available' => true,
                'preparation_time' => 8,
                'rating' => 4.7,
                'total_reviews' => 56,
            ],
        ];

        // Beverages
        $beverages = [
            [
                'restaurant_id' => $pizzaRestaurant->id,
                'category_id' => $beverageCategory->id,
                'name' => 'Coca Cola',
                'description' => 'Classic soft drink',
                'price' => 2.99,
                'is_vegetarian' => true,
                'is_vegan' => true,
                'is_available' => true,
                'preparation_time' => 2,
                'rating' => 4.5,
                'total_reviews' => 120,
            ],
            [
                'restaurant_id' => $burgerRestaurant->id,
                'category_id' => $beverageCategory->id,
                'name' => 'Fresh Orange Juice',
                'description' => 'Freshly squeezed orange juice',
                'price' => 4.99,
                'is_vegetarian' => true,
                'is_vegan' => true,
                'is_available' => true,
                'preparation_time' => 5,
                'rating' => 4.6,
                'total_reviews' => 45,
            ],
        ];

        // Sushi Express Menu
        $sushiRestaurant = Restaurant::where('name', 'Sushi Express')->first();
        
        $sushiItems = [
            [
                'restaurant_id' => $sushiRestaurant->id,
                'category_id' => $sushiCategory->id,
                'name' => 'California Roll',
                'description' => 'Crab, avocado, and cucumber wrapped in rice and seaweed',
                'price' => 12.99,
                'is_available' => true,
                'is_featured' => true,
                'preparation_time' => 15,
                'ingredients' => ['Crab', 'Avocado', 'Cucumber', 'Rice', 'Nori'],
                'rating' => 4.6,
                'total_reviews' => 85,
            ],
            [
                'restaurant_id' => $sushiRestaurant->id,
                'category_id' => $sushiCategory->id,
                'name' => 'Salmon Nigiri',
                'description' => 'Fresh salmon over pressed sushi rice',
                'price' => 8.99,
                'is_available' => true,
                'is_featured' => true,
                'preparation_time' => 10,
                'ingredients' => ['Fresh Salmon', 'Sushi Rice', 'Wasabi'],
                'rating' => 4.8,
                'total_reviews' => 120,
            ],
            [
                'restaurant_id' => $sushiRestaurant->id,
                'category_id' => $sushiCategory->id,
                'name' => 'Spicy Tuna Roll',
                'description' => 'Spicy tuna with cucumber and sesame seeds',
                'price' => 14.99,
                'is_spicy' => true,
                'is_available' => true,
                'preparation_time' => 15,
                'ingredients' => ['Tuna', 'Spicy Mayo', 'Cucumber', 'Sesame Seeds'],
                'rating' => 4.7,
                'total_reviews' => 95,
            ],
            [
                'restaurant_id' => $sushiRestaurant->id,
                'category_id' => $sushiCategory->id,
                'name' => 'Dragon Roll',
                'description' => 'Eel and cucumber topped with avocado and eel sauce',
                'price' => 16.99,
                'is_available' => true,
                'is_featured' => true,
                'preparation_time' => 20,
                'ingredients' => ['Eel', 'Cucumber', 'Avocado', 'Eel Sauce'],
                'rating' => 4.9,
                'total_reviews' => 110,
            ],
            [
                'restaurant_id' => $sushiRestaurant->id,
                'category_id' => $sushiCategory->id,
                'name' => 'Vegetable Tempura Roll',
                'description' => 'Crispy tempura vegetables with sweet sauce',
                'price' => 11.99,
                'is_vegetarian' => true,
                'is_available' => true,
                'preparation_time' => 12,
                'ingredients' => ['Tempura Vegetables', 'Rice', 'Nori', 'Sweet Sauce'],
                'rating' => 4.4,
                'total_reviews' => 65,
            ],
            [
                'restaurant_id' => $sushiRestaurant->id,
                'category_id' => $sushiCategory->id,
                'name' => 'Rainbow Roll',
                'description' => 'California roll topped with assorted fresh fish',
                'price' => 18.99,
                'is_available' => true,
                'is_featured' => true,
                'preparation_time' => 18,
                'ingredients' => ['Tuna', 'Salmon', 'Yellowtail', 'Avocado', 'Crab'],
                'rating' => 4.9,
                'total_reviews' => 140,
            ],
        ];

        $allItems = array_merge($pizzaItems, $burgerItems, $pastaItems, $sushiItems, $desserts, $beverages);

        // Approximate nutrition per serving: [calories, protein, carbs, fats, weight_loss, weight_gain, maintenance]
        $nutrition = [
            'Margherita Pizza' => [800, 32, 98, 28, false, true, true],
            'Pepperoni Pizza' => [950, 40, 96, 42, false, true, false],
            'Vegetarian Supreme' => [720, 28, 95, 24, false, true, true],
            'Classic Cheeseburger' => [700, 38, 45, 38, false, true, false],
            'Bacon Deluxe Burger' => [900, 48, 48, 55, false, true, false],
            'Veggie Burger' => [480, 22, 55, 16, true, false, true],
            'Spaghetti Carbonara' => [750, 30, 85, 32, false, true, true],
            'Penne Arrabbiata' => [550, 16, 90, 12, true, false, true],
            'Fettuccine Alfredo' => [970, 28, 92, 52, false, true, false],
            'Tiramisu' => [450, 7, 45, 26, false, true, false],
            'Chocolate Brownie' => [420, 5, 55, 22, false, true, false],
            'Coca Cola' => [140, 0, 39, 0, false, false, false],
            'Fresh Orange Juice' => [110, 2, 26, 0, true, false, true],
            'California Roll' => [300, 9, 38, 7, true, false, true],
            'Salmon Nigiri' => [280, 18, 32, 8, true, false, true],
            'Spicy Tuna Roll' => [330, 24, 30, 11, true, false, true],
            'Dragon Roll' => [520, 22, 60, 20, false, true, true],
            'Vegetable Tempura Roll' => [420, 8, 60, 16, false, false, true],
            'Rainbow Roll' => [480, 30, 45, 18, true, true, true],
        ];

        foreach ($allItems as $item) {
            if (isset($nutrition[$item['name']])) {
                [$cal, $protein, $carbs, $fats, $loss, $gain, $maintain] = $nutrition[$item['name']];
                $item += [
                    'calories' => $cal,
                    'protein' => $protein,
                    'carbs' => $carbs,
                    'fats' => $fats,
                    'is_weight_loss' => $loss,
                    'is_weight_gain' => $gain,
                    'is_maintenance' => $maintain,
                ];
            }

            MenuItem::create($item);
        }
    }
}
