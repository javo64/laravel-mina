<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 20); $table->string('name', 150); $table->string('item_type', 20)->default('Ambos');
            $table->boolean('is_active')->default(true); $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('product_subgroups', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_group_id')->constrained()->restrictOnDelete();
            $table->string('code', 20); $table->string('name', 150); $table->string('account', 50)->nullable();
            $table->string('inventory_account', 50)->nullable(); $table->string('expense_type', 100)->nullable();
            $table->boolean('is_active')->default(true); $table->timestamps();
            $table->unique(['product_group_id', 'code']);
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('product_group_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('product_subgroup_id')->nullable()->after('product_group_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void { $table->dropConstrainedForeignId('product_subgroup_id'); $table->dropConstrainedForeignId('product_group_id'); });
        Schema::dropIfExists('product_subgroups'); Schema::dropIfExists('product_groups');
    }
};
