<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->text('description')->nullable();
            $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('measurement_units', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->string('symbol', 15)->unique();
            $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['manages_lots', 'manages_series']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('manages_lots')->default(false);
            $table->boolean('manages_series')->default(false);
        });
        Schema::dropIfExists('measurement_units');
        Schema::dropIfExists('product_categories');
    }
};
