<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('requirements')->orderBy('id')->each(function ($requirement): void {
            $statuses = DB::table('requirement_items')
                ->where('requirement_id', $requirement->id)
                ->pluck('approval_status');
            $status = $statuses->isEmpty() || $statuses->every(fn ($itemStatus) => $itemStatus === 'Pendiente')
                ? 'Pendiente'
                : ($statuses->every(fn ($itemStatus) => $itemStatus === 'Aprobado') ? 'Aprobado total' : 'Aprobado parcial');

            DB::table('requirements')->where('id', $requirement->id)->update(['status' => $status]);
        });
    }

    public function down(): void
    {
        // Los nombres anteriores eran ambiguos; no se revierten decisiones históricas.
    }
};
