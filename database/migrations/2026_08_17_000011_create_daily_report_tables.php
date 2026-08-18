<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_report_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scope')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('use_gps')->default(false);
            $table->boolean('evaluator_location')->default(false);
            $table->boolean('allow_export')->default(false);
            $table->boolean('exact_search')->default(false);
            $table->boolean('allow_update')->default(false);
            $table->boolean('auto_collapse')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('daily_report_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_form_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('section')->default('Datos generales');
            $table->string('name');
            $table->string('type', 40);
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->text('formula')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('copy_previous')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['daily_report_form_id', 'field_key']);
        });

        Schema::create('daily_report_form_user', function (Blueprint $table) {
            $table->foreignId('daily_report_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['daily_report_form_id', 'user_id']);
        });

        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_form_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('reported_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('responses');
            $table->string('status')->default('Registrado');
            $table->timestamps();
        });

        DB::table('users')->orderBy('id')->each(function ($user) {
            $permissions = json_decode($user->permissions ?? '[]', true) ?: [];
            if (in_array('users', $permissions, true) && ! in_array('daily-reports', $permissions, true)) {
                $permissions[] = 'daily-reports';
                DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($permissions)]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
        Schema::dropIfExists('daily_report_form_user');
        Schema::dropIfExists('daily_report_fields');
        Schema::dropIfExists('daily_report_forms');
    }
};
