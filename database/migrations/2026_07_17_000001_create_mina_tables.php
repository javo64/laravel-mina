<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('branch')->default('Almacén principal');
            $table->string('profile')->default('Consulta');
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable();
            $table->timestamp('last_access_at')->nullable();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->string('name'); $table->string('type')->default('Producto');
            $table->string('category')->nullable(); $table->string('unit')->default('Unidad'); $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(0); $table->decimal('price', 12, 2)->default(0); $table->boolean('includes_tax')->default(true);
            $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('requirements', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->date('requested_at'); $table->string('responsible');
            $table->string('project'); $table->string('area')->nullable(); $table->string('priority')->default('Media');
            $table->string('status')->default('Pendiente'); $table->timestamp('decision_at')->nullable();
            $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('requirement_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); $table->string('product_name');
            $table->string('category')->nullable(); $table->text('description')->nullable(); $table->decimal('quantity', 12, 2); $table->string('unit'); $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('requirement_items'); Schema::dropIfExists('requirements'); Schema::dropIfExists('products');
        Schema::table('users', function (Blueprint $table) { $table->dropColumn(['branch', 'profile', 'is_active', 'permissions', 'last_access_at']); });
    }
};
