<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id']);
        });
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->dateTime('occurred_at');
            $table->string('type', 30);
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('business_partners')->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('product_reception_item_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        $defaultWarehouse = DB::table('warehouses')->orderBy('id')->first();
        if (! $defaultWarehouse) return;
        $warehouses = DB::table('warehouses')->get()->keyBy(fn ($warehouse) => mb_strtolower($warehouse->name));
        DB::table('products')->where('type', 'Producto')->orderBy('id')->each(function ($product) use ($defaultWarehouse, $warehouses): void {
            $warehouseId = $warehouses->get(mb_strtolower($product->warehouse ?: ''))?->id ?? $defaultWarehouse->id;
            DB::table('inventory_stocks')->insert(['product_id'=>$product->id,'warehouse_id'=>$warehouseId,'quantity'=>$product->stock,'created_at'=>now(),'updated_at'=>now()]);
        });
        DB::table('product_reception_items')->join('product_receptions','product_receptions.id','=','product_reception_items.product_reception_id')->whereNotNull('product_reception_items.product_id')->select('product_reception_items.*','product_receptions.received_at','product_receptions.warehouse','product_receptions.code as reception_code','product_receptions.received_by')->orderBy('product_reception_items.id')->each(function ($item) use ($defaultWarehouse, $warehouses): void {
            $warehouseId = $warehouses->get(mb_strtolower($item->warehouse ?: ''))?->id ?? $defaultWarehouse->id;
            DB::table('inventory_movements')->insert(['code'=>'REC-'.str_pad((string)$item->id,6,'0',STR_PAD_LEFT),'occurred_at'=>$item->received_at.' 12:00:00','type'=>'Entrada','product_id'=>$item->product_id,'destination_warehouse_id'=>$warehouseId,'quantity'=>$item->quantity,'reference'=>$item->reception_code,'product_reception_item_id'=>$item->id,'created_by'=>$item->received_by,'created_at'=>now(),'updated_at'=>now()]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
    }
};
