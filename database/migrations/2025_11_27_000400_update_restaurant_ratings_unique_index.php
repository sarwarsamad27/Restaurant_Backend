<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('restaurant_ratings')) {
            return;
        }

        Schema::table('restaurant_ratings', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_ratings', 'restaurant_id') && Schema::hasColumn('restaurant_ratings', 'user_id')) {
                $table->dropUnique('restaurant_ratings_restaurant_id_user_id_order_id_unique');
            }
        });

        $duplicates = DB::table('restaurant_ratings')
            ->select('restaurant_id', 'user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('restaurant_id', 'user_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = DB::table('restaurant_ratings')
                ->where('restaurant_id', $duplicate->restaurant_id)
                ->where('user_id', $duplicate->user_id)
                ->orderByDesc('created_at')
                ->pluck('id');

            // Keep the most recent rating and remove others
            $idsToDelete = $ids->slice(1);

            if ($idsToDelete->isNotEmpty()) {
                DB::table('restaurant_ratings')->whereIn('id', $idsToDelete)->delete();
            }
        }

        Schema::table('restaurant_ratings', function (Blueprint $table) {
            $table->unique(['restaurant_id', 'user_id'], 'restaurant_ratings_restaurant_id_user_id_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('restaurant_ratings')) {
            return;
        }

        Schema::table('restaurant_ratings', function (Blueprint $table) {
            $table->dropUnique('restaurant_ratings_restaurant_id_user_id_unique');
            $table->unique(['restaurant_id', 'user_id', 'order_id'], 'restaurant_ratings_restaurant_id_user_id_order_id_unique');
        });
    }
};
