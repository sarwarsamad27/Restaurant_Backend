<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'description' => '10% off on your first order',
                'type' => 'percentage',
                'value' => 10,
                'min_order_amount' => 20,
                'max_discount' => 10,
                'usage_limit' => 100,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'SAVE5',
                'description' => '$5 off on orders above $30',
                'type' => 'fixed',
                'value' => 5,
                'min_order_amount' => 30,
                'usage_limit' => 200,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(2),
                'is_active' => true,
            ],
            [
                'code' => 'FREESHIP',
                'description' => 'Free delivery on all orders',
                'type' => 'fixed',
                'value' => 5,
                'min_order_amount' => 25,
                'usage_limit' => 150,
                'valid_from' => now(),
                'valid_until' => now()->addMonth(),
                'is_active' => true,
            ],
            [
                'code' => 'WEEKEND20',
                'description' => '20% off on weekend orders',
                'type' => 'percentage',
                'value' => 20,
                'min_order_amount' => 40,
                'max_discount' => 15,
                'usage_limit' => 50,
                'valid_from' => now(),
                'valid_until' => now()->addWeeks(4),
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::create($coupon);
        }
    }
}
