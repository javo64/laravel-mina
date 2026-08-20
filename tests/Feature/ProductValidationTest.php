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

    public function test_product_requires_complete_information_positive_price_and_stock_and_saves_uppercase(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);
        ProductCategory::create(['name'=>'Repuestos','is_active'=>true]);
        MeasurementUnit::create(['name'=>'Unidad','symbol'=>'UND','is_active'=>true]);
        $base = ['type'=>'Producto','name'=>'filtro de aire','secondary_name'=>'filtro principal','description'=>'para motor','barcode'=>'123456789','category'=>'Repuestos','unit'=>'Unidad','currency'=>'PEN','stock'=>1,'min_stock'=>1,'warehouse'=>'Almacén principal','tax_affectation'=>'Gravado - Operación onerosa'];

        $this->actingAs($user)->post(route('products.store'), [...$base, 'price'=>0, 'stock'=>0])
            ->assertSessionHasErrors(['price','stock']);
        $this->actingAs($user)->post(route('products.store'), [...$base, 'price'=>12.5])
            ->assertRedirect();
        $this->assertDatabaseHas('products',['name'=>'FILTRO DE AIRE','secondary_name'=>'FILTRO PRINCIPAL','description'=>'PARA MOTOR','price'=>12.5,'stock'=>1]);
    }
}
