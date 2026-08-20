<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) { $table->id(); $table->string('name',150)->unique(); $table->string('code',50)->nullable()->unique(); $table->string('address')->nullable(); $table->boolean('is_active')->default(true); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); });
        Schema::create('warehouses', function (Blueprint $table) { $table->id(); $table->foreignId('branch_id')->constrained()->cascadeOnDelete(); $table->string('name',150); $table->string('code',50)->nullable()->unique(); $table->string('address')->nullable(); $table->boolean('is_active')->default(true); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->unique(['branch_id','name']); });
        Schema::create('banks', function (Blueprint $table) { $table->id(); $table->string('name',150)->unique(); $table->string('code',50)->nullable()->unique(); $table->boolean('is_active')->default(true); $table->timestamps(); });
        Schema::table('bank_accounts', function (Blueprint $table) { $table->foreignId('bank_id')->nullable()->after('business_partner_id')->constrained('banks')->nullOnDelete(); });

        $branchId = DB::table('branches')->insertGetId(['name'=>'Sucursal principal','code'=>'001','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('warehouses')->insert(['branch_id'=>$branchId,'name'=>'Almacén principal','code'=>'001','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('bank_accounts')->select('bank_name')->whereNotNull('bank_name')->distinct()->orderBy('bank_name')->get()->each(function ($account) { if (!DB::table('banks')->where('name',$account->bank_name)->exists()) DB::table('banks')->insert(['name'=>$account->bank_name,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]); });
        DB::table('bank_accounts')->whereNull('bank_id')->orderBy('id')->get()->each(function ($account) { $bankId=DB::table('banks')->where('name',$account->bank_name)->value('id'); if($bankId) DB::table('bank_accounts')->where('id',$account->id)->update(['bank_id'=>$bankId]); });
    }
    public function down(): void { Schema::table('bank_accounts', fn (Blueprint $table) => $table->dropConstrainedForeignId('bank_id')); Schema::dropIfExists('banks'); Schema::dropIfExists('warehouses'); Schema::dropIfExists('branches'); }
};
