<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('rating_taste');
            $table->tinyInteger('rating_quantity');
            $table->tinyInteger('rating_hygiene');
            $table->tinyInteger('rating_value');
            $table->decimal('average_score', 3, 2);
            $table->timestamps();

            $table->unique(['restaurant_id', 'user_id', 'order_id']);
            $table->index(['restaurant_id', 'average_score']);
        });

        // Backfill from existing reviews to ensure continuity
        $reviews = DB::table('reviews')->select(
            'id',
            'restaurant_id',
            'user_id',
            'order_id',
            'restaurant_rating'
        )->get();

        $insertRows = [];

        foreach ($reviews as $review) {
            if (!$review->restaurant_id || !$review->user_id) {
                continue;
            }

            $rating = max(1, min((int) $review->restaurant_rating, 5));

            $insertRows[] = [
                'restaurant_id' => $review->restaurant_id,
                'user_id' => $review->user_id,
                'order_id' => $review->order_id,
                'rating_taste' => $rating,
                'rating_quantity' => $rating,
                'rating_hygiene' => $rating,
                'rating_value' => $rating,
                'average_score' => number_format($rating, 2),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($insertRows)) {
            DB::table('restaurant_ratings')->insert($insertRows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_ratings');
    }
};
