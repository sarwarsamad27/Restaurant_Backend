<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('role', 'restaurant_owner')->first();

        $restaurants = [
            [
                'owner_id' => $owner->id,
                'name' => 'The Hungry Spoon',
                'description' => 'Delicious homemade meals and comfort food',
                'phone' => '+92999999999',
                'email' => 'contact@thehungryspoon.com',
                'address' => '123 Main Street, XYZ City',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'opening_hours' => [
                    'monday' => ['open' => '10:00', 'close' => '22:00'],
                    'tuesday' => ['open' => '10:00', 'close' => '22:00'],
                    'wednesday' => ['open' => '10:00', 'close' => '22:00'],
                    'thursday' => ['open' => '10:00', 'close' => '22:00'],
                    'friday' => ['open' => '10:00', 'close' => '23:00'],
                    'saturday' => ['open' => '10:00', 'close' => '23:00'],
                    'sunday' => ['open' => '11:00', 'close' => '21:00'],
                ],
                'delivery_fee' => 5.00,
                'delivery_time' => 30,
                'minimum_order' => 15.00,
                'rating' => 4.5,
                'total_reviews' => 150,
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'owner_id' => $owner->id,
                'name' => 'Burger House',
                'description' => 'Gourmet burgers made with premium ingredients',
                'phone' => '+1234567891',
                'email' => 'info@burgerhouse.com',
                'address' => '456 Burger Ave, New York, NY 10002',
                'latitude' => 40.7200,
                'longitude' => -74.0100,
                'opening_hours' => [
                    'monday' => ['open' => '11:00', 'close' => '22:00'],
                    'tuesday' => ['open' => '11:00', 'close' => '22:00'],
                    'wednesday' => ['open' => '11:00', 'close' => '22:00'],
                    'thursday' => ['open' => '11:00', 'close' => '22:00'],
                    'friday' => ['open' => '11:00', 'close' => '23:00'],
                    'saturday' => ['open' => '11:00', 'close' => '23:00'],
                    'sunday' => ['open' => '12:00', 'close' => '21:00'],
                ],
                'delivery_fee' => 4.00,
                'delivery_time' => 25,
                'minimum_order' => 12.00,
                'rating' => 4.3,
                'total_reviews' => 98,
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'owner_id' => $owner->id,
                'name' => 'Pasta Palace',
                'description' => 'Authentic Italian pasta and Mediterranean cuisine',
                'phone' => '+1234567892',
                'email' => 'hello@pastapalace.com',
                'address' => '789 Pasta Lane, New York, NY 10003',
                'latitude' => 40.7300,
                'longitude' => -74.0150,
                'opening_hours' => [
                    'monday' => ['open' => '12:00', 'close' => '22:00'],
                    'tuesday' => ['open' => '12:00', 'close' => '22:00'],
                    'wednesday' => ['open' => '12:00', 'close' => '22:00'],
                    'thursday' => ['open' => '12:00', 'close' => '22:00'],
                    'friday' => ['open' => '12:00', 'close' => '23:00'],
                    'saturday' => ['open' => '12:00', 'close' => '23:00'],
                    'sunday' => ['open' => '12:00', 'close' => '22:00'],
                ],
                'delivery_fee' => 6.00,
                'delivery_time' => 35,
                'minimum_order' => 20.00,
                'rating' => 4.7,
                'total_reviews' => 210,
                'status' => 'active',
                'is_featured' => false,
            ],
            [
                'owner_id' => $owner->id,
                'name' => 'Sushi Express',
                'description' => 'Fresh sushi and Japanese cuisine',
                'phone' => '+1234567893',
                'email' => 'order@sushiexpress.com',
                'address' => '321 Sushi Blvd, New York, NY 10004',
                'latitude' => 40.7400,
                'longitude' => -74.0200,
                'opening_hours' => [
                    'monday' => ['open' => '11:30', 'close' => '21:30'],
                    'tuesday' => ['open' => '11:30', 'close' => '21:30'],
                    'wednesday' => ['open' => '11:30', 'close' => '21:30'],
                    'thursday' => ['open' => '11:30', 'close' => '21:30'],
                    'friday' => ['open' => '11:30', 'close' => '22:30'],
                    'saturday' => ['open' => '11:30', 'close' => '22:30'],
                    'sunday' => ['open' => '12:00', 'close' => '21:00'],
                ],
                'delivery_fee' => 7.00,
                'delivery_time' => 40,
                'minimum_order' => 25.00,
                'rating' => 4.6,
                'total_reviews' => 175,
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'owner_id' => $owner->id,
                'name' => 'Taco Fiesta',
                'description' => 'Authentic Mexican tacos and burritos',
                'phone' => '+1234567894',
                'email' => 'contact@tacofiesta.com',
                'address' => '654 Taco Street, New York, NY 10005',
                'latitude' => 40.7500,
                'longitude' => -74.0250,
                'opening_hours' => [
                    'monday' => ['open' => '11:00', 'close' => '22:00'],
                    'tuesday' => ['open' => '11:00', 'close' => '22:00'],
                    'wednesday' => ['open' => '11:00', 'close' => '22:00'],
                    'thursday' => ['open' => '11:00', 'close' => '22:00'],
                    'friday' => ['open' => '11:00', 'close' => '23:00'],
                    'saturday' => ['open' => '11:00', 'close' => '23:00'],
                    'sunday' => ['open' => '11:00', 'close' => '22:00'],
                ],
                'delivery_fee' => 4.50,
                'delivery_time' => 28,
                'minimum_order' => 10.00,
                'rating' => 4.4,
                'total_reviews' => 132,
                'status' => 'active',
                'is_featured' => false,
            ],
        ];

        foreach ($restaurants as $restaurant) {
            Restaurant::create($restaurant);
        }
    }
}
