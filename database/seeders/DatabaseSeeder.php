<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Safe to run on every deploy: only seeds an empty database
        if (User::exists()) {
            $this->command->info('Database already has data, skipping seeders.');
            return;
        }

        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            RestaurantSeeder::class,
            MenuItemSeeder::class,
            CouponSeeder::class,
        ]);
    }
}
