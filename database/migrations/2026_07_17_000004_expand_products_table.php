<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('secondary_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('secondary_name');
            $table->string('currency', 3)->default('PEN')->after('unit');
            $table->string('barcode')->nullable()->after('code');
            $table->string('warehouse')->default('Almacén principal')->after('barcode');
            $table->string('tax_affectation')->default('Gravado - Operación onerosa')->after('includes_tax');
            $table->boolean('manages_lots')->default(false)->after('tax_affectation');
            $table->boolean('manages_series')->default(false)->after('manages_lots');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['secondary_name','description','currency','barcode','warehouse','tax_affectation','manages_lots','manages_series']);
        });
    }
};
