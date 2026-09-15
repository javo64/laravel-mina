<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('quotation_processes',function(Blueprint $table){$table->id();$table->foreignId('requirement_id')->unique()->constrained()->cascadeOnDelete();$table->string('status',40)->default('Pendiente cotización');$table->unsignedInteger('version')->default(1);$table->text('approval_observation')->nullable();$table->timestamp('submitted_at')->nullable();$table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('decided_at')->nullable();$table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamps();});
  Schema::create('requirement_quotations',function(Blueprint $table){$table->id();$table->foreignId('quotation_process_id')->constrained()->cascadeOnDelete();$table->foreignId('supplier_id')->constrained('business_partners')->restrictOnDelete();$table->string('path');$table->string('original_name');$table->string('mime_type',100);$table->unsignedBigInteger('size');$table->decimal('amount',14,2)->nullable();$table->string('currency',3)->default('PEN');$table->boolean('is_winner')->default(false);$table->timestamps();$table->unique(['quotation_process_id','supplier_id']);});
  Schema::table('purchase_order_quotations',fn(Blueprint $table)=>$table->foreignId('requirement_quotation_id')->nullable()->after('purchase_order_id')->constrained()->nullOnDelete());
 }
 public function down():void{Schema::table('purchase_order_quotations',fn(Blueprint $table)=>$table->dropConstrainedForeignId('requirement_quotation_id'));Schema::dropIfExists('requirement_quotations');Schema::dropIfExists('quotation_processes');}
};
