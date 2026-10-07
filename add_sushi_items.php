<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Restaurant;
use App\Models\Category;
use App\Models\MenuItem;

$sushiRestaurant = Restaurant::where('name', 'Sushi Express')->first();
$sushiCategory = Category::where('name', 'Sushi')->first();

if (!$sushiRestaurant || !$sushiCategory) {
    echo "Restaurant or category not found!\n";
    exit(1);
}

$sushiItems = [
    [
        'name' => 'California Roll',
        'description' => 'Crab, avocado, and cucumber wrapped in rice and seaweed',
        'price' => 12.99,
        'ingredients' => ['Crab', 'Avocado', 'Cucumber', 'Rice', 'Nori'],
        'rating' => 4.6,
        'total_reviews' => 85,
    ],
    [
        'name' => 'Salmon Nigiri',
        'description' => 'Fresh salmon over pressed sushi rice',
        'price' => 8.99,
        'ingredients' => ['Fresh Salmon', 'Sushi Rice', 'Wasabi'],
        'rating' => 4.8,
        'total_reviews' => 120,
    ],
    [
        'name' => 'Spicy Tuna Roll',
        'description' => 'Spicy tuna with cucumber and sesame seeds',
        'price' => 14.99,
        'is_spicy' => true,
        'ingredients' => ['Tuna', 'Spicy Mayo', 'Cucumber', 'Sesame Seeds'],
        'rating' => 4.7,
        'total_reviews' => 95,
    ],
    [
        'name' => 'Dragon Roll',
        'description' => 'Eel and cucumber topped with avocado and eel sauce',
        'price' => 16.99,
        'ingredients' => ['Eel', 'Cucumber', 'Avocado', 'Eel Sauce'],
        'rating' => 4.9,
        'total_reviews' => 110,
    ],
    [
        'name' => 'Vegetable Tempura Roll',
        'description' => 'Crispy tempura vegetables with sweet sauce',
        'price' => 11.99,
        'is_vegetarian' => true,
        'ingredients' => ['Tempura Vegetables', 'Rice', 'Nori', 'Sweet Sauce'],
        'rating' => 4.4,
        'total_reviews' => 65,
    ],
    [
        'name' => 'Rainbow Roll',
        'description' => 'California roll topped with assorted fresh fish',
        'price' => 18.99,
        'ingredients' => ['Tuna', 'Salmon', 'Yellowtail', 'Avocado', 'Crab'],
        'rating' => 4.9,
        'total_reviews' => 140,
    ],
];

foreach ($sushiItems as $item) {
    MenuItem::create([
        'restaurant_id' => $sushiRestaurant->id,
        'category_id' => $sushiCategory->id,
        'name' => $item['name'],
        'description' => $item['description'],
        'price' => $item['price'],
        'is_vegetarian' => $item['is_vegetarian'] ?? false,
        'is_spicy' => $item['is_spicy'] ?? false,
        'is_available' => true,
        'is_featured' => true,
        'preparation_time' => 15,
        'ingredients' => $item['ingredients'],
        'rating' => $item['rating'],
        'total_reviews' => $item['total_reviews'],
    ]);
    echo "Added: {$item['name']}\n";
}

echo "\nAll sushi items added successfully!\n";
