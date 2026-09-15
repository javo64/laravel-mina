<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultWarehouse = DB::table('warehouses')->where('is_active', true)->orderBy('id')->first();
        if (! $defaultWarehouse) return;
        $warehouses = DB::table('warehouses')->get()->keyBy(fn ($warehouse) => mb_strtolower($warehouse->name));
        DB::table('products')->where('type', 'Producto')->orderBy('id')->each(function ($product) use ($defaultWarehouse, $warehouses): void {
            if (DB::table('inventory_stocks')->where('product_id', $product->id)->exists()) return;
            $warehouseId = $warehouses->get(mb_strtolower($product->warehouse ?: ''))?->id ?? $defaultWarehouse->id;
            DB::table('inventory_stocks')->insert(['product_id'=>$product->id,'warehouse_id'=>$warehouseId,'quantity'=>$product->stock,'created_at'=>now(),'updated_at'=>now()]);
        });
    }

    public function down(): void {}
};
