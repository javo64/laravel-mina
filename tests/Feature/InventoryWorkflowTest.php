<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BusinessPartner;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reception_registers_stock_in_selected_warehouse(): void
    {
        [$user,$product,$origin] = $this->inventoryFixtures();

        $this->actingAs($user)->post(route('product-receptions.store'), [
            'received_at'=>'2026-08-27', 'warehouse'=>$origin->name,
            'items'=>[['product_id'=>$product->id,'quantity'=>8]],
        ])->assertRedirect(route('product-receptions.index'));

        $this->assertDatabaseHas('inventory_stocks',['product_id'=>$product->id,'warehouse_id'=>$origin->id,'quantity'=>8]);
        $this->assertDatabaseHas('inventory_movements',['type'=>'Entrada','product_id'=>$product->id,'destination_warehouse_id'=>$origin->id,'quantity'=>8]);
    }

    public function test_transfer_moves_stock_without_changing_product_total(): void
    {
        [$user,$product,$origin,$destination] = $this->inventoryFixtures(true);
        $product->update(['stock'=>12]);
        InventoryStock::create(['product_id'=>$product->id,'warehouse_id'=>$origin->id,'quantity'=>12]);

        $this->actingAs($user)->post(route('inventory.transfers.store'), [
            'occurred_at'=>'2026-08-27','product_id'=>$product->id,
            'source_warehouse_id'=>$origin->id,'destination_warehouse_id'=>$destination->id,
            'quantity'=>5,'notes'=>'Reposición de sede',
        ])->assertRedirect(route('inventory.index',['tab'=>'movimientos']));

        $this->assertDatabaseHas('inventory_stocks',['product_id'=>$product->id,'warehouse_id'=>$origin->id,'quantity'=>7]);
        $this->assertDatabaseHas('inventory_stocks',['product_id'=>$product->id,'warehouse_id'=>$destination->id,'quantity'=>5]);
        $this->assertSame(12,(int)$product->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements',['type'=>'Traslado','quantity'=>5]);
    }

    public function test_supplier_return_creates_document_and_reduces_stock(): void
    {
        [$user,$product,$origin] = $this->inventoryFixtures();
        $product->update(['stock'=>12]);
        InventoryStock::create(['product_id'=>$product->id,'warehouse_id'=>$origin->id,'quantity'=>12]);
        $supplier=BusinessPartner::create(['type'=>'Proveedor','document_type'=>'RUC','document_number'=>'20123456789','name'=>'PROVEEDOR INDUSTRIAL','is_active'=>true]);

        $this->actingAs($user)->post(route('inventory.returns.store'), [
            'occurred_at'=>'2026-08-27','supplier_id'=>$supplier->id,'product_id'=>$product->id,
            'source_warehouse_id'=>$origin->id,'quantity'=>3,'reference'=>'GUIA DEV-001',
        ])->assertRedirect(route('inventory.index',['tab'=>'devoluciones']));

        $this->assertSame(9,(int)$product->fresh()->stock);
        $this->assertDatabaseHas('inventory_stocks',['product_id'=>$product->id,'warehouse_id'=>$origin->id,'quantity'=>9]);
        $movement=InventoryMovement::where('type','Devolución')->firstOrFail();
        $this->assertStringStartsWith('DEV-2026-',$movement->code);
        $this->assertSame($supplier->id,$movement->supplier_id);
    }

    public function test_inventory_tabs_are_available_to_warehouse_users(): void
    {
        [$user,$product,$warehouse] = $this->inventoryFixtures();
        InventoryStock::create(['product_id'=>$product->id,'warehouse_id'=>$warehouse->id,'quantity'=>0]);
        foreach (['movimientos','kardex','inventario','devoluciones'] as $tab) {
            $this->actingAs($user)->get(route('inventory.index',['tab'=>$tab]))->assertOk()->assertSee('Inventario');
        }
        $this->actingAs($user)->get(route('inventory.index',['tab'=>'movimientos']))
            ->assertSee('Productos en almacén')->assertSee('Rodamiento industrial')->assertSee('0');
    }

    private function inventoryFixtures(bool $secondWarehouse=false): array
    {
        $user=User::factory()->create(['permissions'=>['products']]);
        $origin=Warehouse::with('branch')->firstOrFail();
        $product=Product::create(['code'=>'PRD-INV-01','warehouse'=>$origin->name,'name'=>'Rodamiento industrial','type'=>'Producto','category'=>'Repuestos','unit'=>'Unidad','currency'=>'PEN','stock'=>0,'min_stock'=>1,'price'=>25,'includes_tax'=>true,'tax_affectation'=>'Gravado','is_active'=>true]);
        if (! $secondWarehouse) return [$user,$product,$origin];
        $branch=Branch::create(['name'=>'Sucursal secundaria','code'=>'SUC-TEST','is_active'=>true]);
        $destination=Warehouse::create(['branch_id'=>$branch->id,'name'=>'Almacén secundario','code'=>'ALM-TEST','is_active'=>true]);
        return [$user,$product,$origin,$destination];
    }
}
