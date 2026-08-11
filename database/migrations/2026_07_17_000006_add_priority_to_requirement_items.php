<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('requirement_items', function (Blueprint $table) {
            $table->string('priority')->default('Media')->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('requirement_items', fn (Blueprint $table) => $table->dropColumn('priority'));
    }
};
