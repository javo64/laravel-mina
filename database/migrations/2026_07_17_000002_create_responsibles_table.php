<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('responsibles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('requirements')->select('responsible')->distinct()->orderBy('responsible')->get()->each(
            fn ($row) => DB::table('responsibles')->insert([
                'name' => $row->responsible,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('responsibles');
    }
};
