<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('requirement_items', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->after('product_id')->constrained('cost_centers')->nullOnDelete();
            $table->string('cost_center', 150)->nullable()->after('category');
        });

        if (! DB::table('projects')->where('name', 'MINA CAROLINA JE')->exists()) {
            DB::table('projects')->insert(['name' => 'MINA CAROLINA JE', 'code' => 'MCJ', 'description' => 'Proyecto predeterminado', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('requirement_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropColumn('cost_center');
        });
    }
};
