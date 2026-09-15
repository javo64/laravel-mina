<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirement_items', function (Blueprint $table): void {
            $table->decimal('approved_quantity', 12, 2)->nullable()->after('quantity');
        });

        DB::table('requirement_items')->where('approval_status', 'Aprobado')->update([
            'approved_quantity' => DB::raw('quantity'),
        ]);
    }

    public function down(): void
    {
        Schema::table('requirement_items', function (Blueprint $table): void {
            $table->dropColumn('approved_quantity');
        });
    }
};
