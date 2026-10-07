<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table): void {
            $table->timestamp('reviewed_at')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('requirement_items', function (Blueprint $table): void {
            $table->timestamp('reviewed_at')->nullable()->after('approval_status');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requirement_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });
        Schema::table('requirements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });
    }
};
