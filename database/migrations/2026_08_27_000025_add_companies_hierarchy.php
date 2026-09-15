<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name',150)->unique();
            $table->string('code',50)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('branches', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });
        $plantId=DB::table('companies')->insertGetId(['name'=>'PLANTA FABULOSA','code'=>'EMP-001','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('companies')->insert(['name'=>'MINA CAROLINA JE','code'=>'EMP-002','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('branches')->whereNull('company_id')->update(['company_id'=>$plantId]);
    }

    public function down(): void
    {
        Schema::table('branches', fn(Blueprint $table)=>$table->dropConstrainedForeignId('company_id'));
        Schema::dropIfExists('companies');
    }
};
