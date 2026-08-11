<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_api_settings', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->text('token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('business_partners', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('document_type', 3);
            $table->string('document_number', 11)->unique();
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('department')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('users')->orderBy('id')->each(function ($user) {
            $permissions = json_decode($user->permissions ?: '[]', true) ?: [];
            if ($user->profile === 'Administrador' && ! in_array('logistics', $permissions, true)) {
                $permissions[] = 'logistics';
                DB::table('users')->where('id', $user->id)->update([
                    'permissions' => json_encode(array_values($permissions)),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_partners');
        Schema::dropIfExists('document_api_settings');
    }
};
