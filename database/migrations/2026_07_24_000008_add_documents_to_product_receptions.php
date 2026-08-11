<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_receptions', function (Blueprint $table) {
            $table->string('guide_number')->nullable()->after('supplier');
            $table->string('guide_file')->nullable()->after('guide_number');
            $table->string('invoice_number')->nullable()->after('guide_file');
            $table->string('invoice_file')->nullable()->after('invoice_number');
            $table->string('order_number')->nullable()->after('invoice_file');
            $table->string('order_file')->nullable()->after('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('product_receptions', function (Blueprint $table) {
            $table->dropColumn([
                'guide_number', 'guide_file', 'invoice_number',
                'invoice_file', 'order_number', 'order_file',
            ]);
        });
    }
};
