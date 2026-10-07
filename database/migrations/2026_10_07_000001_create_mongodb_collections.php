<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

/**
 * MongoDB is schema-less, so this only creates the collections and the
 * indexes that keep data unique and lookups fast.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        $indexes = [
            'users' => [['email', true], ['role', false]],
            'categories' => [['slug', true]],
            'restaurants' => [['slug', false], ['owner_id', false], ['status', false]],
            'menu_items' => [['restaurant_id', false], ['category_id', false]],
            'orders' => [['order_number', true], ['user_id', false], ['restaurant_id', false], ['driver_id', false], ['status', false]],
            'order_items' => [['order_id', false]],
            'drivers' => [['user_id', true]],
            'reviews' => [['order_id', false], ['restaurant_id', false]],
            'restaurant_ratings' => [[['restaurant_id', 'user_id'], true]],
            'notifications' => [['user_id', false]],
            'coupons' => [['code', true]],
            'personal_access_tokens' => [['token', true], [['tokenable_type', 'tokenable_id'], false]],
        ];

        foreach ($indexes as $collection => $definitions) {
            Schema::connection($this->connection)->create($collection, function (Blueprint $collection) use ($definitions) {
                foreach ($definitions as [$columns, $unique]) {
                    $unique ? $collection->unique($columns) : $collection->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'categories', 'restaurants', 'menu_items', 'orders', 'order_items', 'drivers', 'reviews', 'restaurant_ratings', 'notifications', 'coupons', 'personal_access_tokens'] as $collection) {
            Schema::connection($this->connection)->dropIfExists($collection);
        }
    }
};
