<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotation_processes', function (Blueprint $table): void {
            $table->dropForeign(['requirement_id']);
            $table->dropUnique('quotation_processes_requirement_id_unique');
            $table->index('requirement_id');
            $table->foreign('requirement_id')->references('id')->on('requirements')->cascadeOnDelete();
            $table->string('block_code', 40)->nullable()->unique()->after('id');
        });

        Schema::create('quotation_process_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_process_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['quotation_process_id', 'requirement_item_id'], 'qpi_process_item_unique');
        });

        DB::table('quotation_processes')->orderBy('id')->each(function (object $process): void {
            $itemIds = DB::table('requirement_items')
                ->where('requirement_id', $process->requirement_id)
                ->whereIn('approval_status', ['Aprobado', 'Aprobado parcial'])
                ->pluck('id');
            foreach ($itemIds as $itemId) {
                DB::table('quotation_process_items')->insert([
                    'quotation_process_id' => $process->id,
                    'requirement_item_id' => $itemId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('quotation_processes')->where('id', $process->id)->update([
                'block_code' => 'COT-'.now()->year.'-'.str_pad((string) $process->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_process_items');
        Schema::table('quotation_processes', function (Blueprint $table): void {
            $table->dropForeign(['requirement_id']);
            $table->dropIndex('quotation_processes_requirement_id_index');
            $table->dropUnique('quotation_processes_block_code_unique');
            $table->dropColumn('block_code');
            $table->unique('requirement_id');
            $table->foreign('requirement_id')->references('id')->on('requirements')->cascadeOnDelete();
        });
    }
};
