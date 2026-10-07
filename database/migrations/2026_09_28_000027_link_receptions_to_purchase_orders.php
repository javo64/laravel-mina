<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->string('receipt_status', 30)->default('Pendiente')->after('status')->index();
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('received_quantity', 12, 2)->default(0)->after('quantity');
        });

        Schema::table('product_receptions', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('product_reception_items', function (Blueprint $table): void {
            $table->foreignId('purchase_order_item_id')->nullable()->after('product_reception_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_reception_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_order_item_id');
        });
        Schema::table('product_receptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_order_id');
        });
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropColumn('received_quantity');
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex(['receipt_status']);
            $table->dropColumn('receipt_status');
        });
    }
};
