<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->integer('calories')->nullable()->after('sort_order');
            $table->integer('protein')->nullable()->after('calories');
            $table->integer('carbs')->nullable()->after('protein');
            $table->integer('fats')->nullable()->after('carbs');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['calories', 'protein', 'carbs', 'fats']);
        });
    }
};
