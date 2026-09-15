<?php

namespace Tests\Feature;

use App\Models\MeasurementUnit;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_be_created_without_price_or_initial_stock_and_saves_uppercase(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);
        ProductCategory::create(['name'=>'Repuestos','is_active'=>true]);
        MeasurementUnit::create(['name'=>'Unidad','symbol'=>'UND','is_active'=>true]);
        $base = ['type'=>'Producto','name'=>'filtro de aire','secondary_name'=>'filtro principal','description'=>'para motor','barcode'=>'123456789','category'=>'Repuestos','unit'=>'Unidad','currency'=>'PEN','stock'=>1,'min_stock'=>1,'warehouse'=>'Almacén principal','tax_affectation'=>'Gravado - Operación onerosa'];

        unset($base['stock']);
        $this->actingAs($user)->post(route('products.store'), $base)->assertRedirect();
        $product=\App\Models\Product::where('name','FILTRO DE AIRE')->firstOrFail();
        $this->assertSame(0,(int)$product->price);
        $this->assertSame(0,(int)$product->stock);
        $this->assertDatabaseHas('inventory_stocks',['product_id'=>$product->id,'quantity'=>0]);
    }
}
