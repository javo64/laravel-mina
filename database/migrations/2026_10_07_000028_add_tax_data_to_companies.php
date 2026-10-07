<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('ruc', 11)->nullable()->unique()->after('code');
            $table->string('legal_name', 150)->nullable()->after('name');
        });

        // Conserva como razón social los nombres de empresas que ya existen.
        \Illuminate\Support\Facades\DB::table('companies')->whereNull('legal_name')->update(['legal_name' => \Illuminate\Support\Facades\DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropUnique(['ruc']);
            $table->dropColumn(['ruc', 'legal_name']);
        });
    }
};
