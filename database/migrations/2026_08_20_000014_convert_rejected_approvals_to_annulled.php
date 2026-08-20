<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('requirement_items')->where('approval_status', 'Rechazado')->update(['approval_status' => 'Anulado']);
        DB::table('requirements')->where('status', 'Rechazado')->update(['status' => 'Anulado']);
    }

    public function down(): void
    {
        // No se revierte para no alterar decisiones históricas.
    }
};
