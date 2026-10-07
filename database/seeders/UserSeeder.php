<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Driver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin User
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@restaurant.com',
            'password' => Hash::make('password'),
            'phone' => '+1234567890',
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Restaurant Owner
        $owner = User::create([
            'name' => 'John Restaurant Owner',
            'email' => 'owner@restaurant.com',
            'password' => Hash::make('password'),
            'phone' => '+1234567891',
            'role' => 'restaurant_owner',
            'status' => 'active',
        ]);

        // Customer Users
        for ($i = 1; $i <= 5; $i++) {
            User::create([
                'name' => "Customer {$i}",
                'email' => "customer{$i}@example.com",
                'password' => Hash::make('password'),
                'phone' => "+123456789{$i}",
                'address' => "123 Main St, City {$i}",
                'latitude' => 40.7128 + ($i * 0.01),
                'longitude' => -74.0060 + ($i * 0.01),
                'role' => 'customer',
                'status' => 'active',
            ]);
        }

        // Driver Users
        for ($i = 1; $i <= 3; $i++) {
            $driver = User::create([
                'name' => "Driver {$i}",
                'email' => "driver{$i}@restaurant.com",
                'password' => Hash::make('password'),
                'phone' => "+123456780{$i}",
                'role' => 'driver',
                'status' => 'active',
            ]);

            Driver::create([
                'user_id' => $driver->id,
                'vehicle_type' => ['bike', 'car', 'scooter'][rand(0, 2)],
                'vehicle_number' => 'ABC' . rand(1000, 9999),
                'license_number' => 'DL' . rand(100000, 999999),
                'current_latitude' => 40.7128,
                'current_longitude' => -74.0060,
                'status' => 'available',
                'is_verified' => true,
                'rating' => rand(40, 50) / 10,
            ]);
        }

        // Staff User
        User::create([
            'name' => 'Restaurant Staff',
            'email' => 'staff@restaurant.com',
            'password' => Hash::make('password'),
            'phone' => '+1234567899',
            'role' => 'staff',
            'status' => 'active',
        ]);
    }
}
