<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_receptions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->date('received_at');
            $table->string('supplier')->nullable();
            $table->string('document_reference')->nullable();
            $table->string('warehouse')->default('Almacén principal');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('product_reception_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_reception_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_code');
            $table->string('product_name');
            $table->string('unit');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reception_items');
        Schema::dropIfExists('product_receptions');
    }
};
