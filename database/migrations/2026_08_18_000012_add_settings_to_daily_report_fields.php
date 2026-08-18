<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_report_fields', fn (Blueprint $table) => $table->json('settings')->nullable()->after('formula'));
        DB::table('daily_report_fields')->where('type', 'select')->update(['type' => 'dropdown']);
    }

    public function down(): void
    {
        Schema::table('daily_report_fields', fn (Blueprint $table) => $table->dropColumn('settings'));
    }
};
