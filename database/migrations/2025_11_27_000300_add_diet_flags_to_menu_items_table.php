<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->boolean('is_weight_loss')->default(false)->after('fats');
            $table->boolean('is_weight_gain')->default(false)->after('is_weight_loss');
            $table->boolean('is_maintenance')->default(false)->after('is_weight_gain');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['is_weight_loss', 'is_weight_gain', 'is_maintenance']);
        });
    }
};
