<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('requirement_items', function (Blueprint $table) {
            $table->string('approval_status')->default('Pendiente')->after('priority');
            $table->timestamp('decision_at')->nullable()->after('approval_status');
            $table->foreignId('decision_by')->nullable()->after('decision_at')->constrained('users')->nullOnDelete();
            $table->index(['approval_status', 'requirement_id']);
        });

        DB::table('requirements')->orderBy('id')->each(function ($requirement): void {
            $status = in_array($requirement->status, ['Aprobado', 'Rechazado', 'Anulado'], true)
                ? $requirement->status
                : 'Pendiente';

            DB::table('requirement_items')->where('requirement_id', $requirement->id)->update([
                'approval_status' => $status,
                'decision_at' => $status === 'Pendiente' ? null : $requirement->decision_at,
                'decision_by' => $status === 'Pendiente' ? null : $requirement->decision_by,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('requirement_items', function (Blueprint $table) {
            $table->dropIndex(['approval_status', 'requirement_id']);
            $table->dropConstrainedForeignId('decision_by');
            $table->dropColumn(['approval_status', 'decision_at']);
        });
    }
};
