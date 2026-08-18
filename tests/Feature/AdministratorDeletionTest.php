<?php

namespace Tests\Feature;

use App\Models\BusinessPartner;
use App\Models\Product;
use App\Models\ProductReception;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministratorDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_administrator_can_delete_catalog_records(): void
    {
        $operator = User::factory()->create(['profile'=>'Almacenero','permissions'=>['products']]);
        $product = $this->product('PRD-NO-ADMIN');

        $this->actingAs($operator)->delete(route('products.destroy', $product))->assertForbidden();
        $this->assertDatabaseHas('products', ['id'=>$product->id]);
    }

    public function test_linked_products_and_suppliers_are_protected(): void
    {
        $admin = $this->admin();
        $linkedProduct = $this->product('PRD-LINKED');
        $freeProduct = $this->product('PRD-FREE');
        $requirement = Requirement::create([
            'code'=>'REQ-2026-0001','requested_at'=>'2026-08-18','responsible'=>'Javier',
            'project'=>'Mina','area'=>'Operaciones','priority'=>'Media','status'=>'Pendiente',
        ]);
        $requirement->items()->create([
            'product_id'=>$linkedProduct->id,'product_name'=>$linkedProduct->name,'quantity'=>1,
            'unit'=>'Unidad','priority'=>'Media',
        ]);

        $this->actingAs($admin)->from(route('products.index'))
            ->delete(route('products.destroy', $linkedProduct))->assertSessionHasErrors();
        $this->actingAs($admin)->delete(route('products.destroy', $freeProduct))->assertRedirect();
        $this->assertDatabaseHas('products', ['id'=>$linkedProduct->id]);
        $this->assertDatabaseMissing('products', ['id'=>$freeProduct->id]);

        $linkedPartner = $this->partner('20111111111', 'PROVEEDOR VINCULADO');
        $freePartner = $this->partner('20222222222', 'PROVEEDOR LIBRE');
        ProductReception::create([
            'code'=>'GRC 001 - 001','received_at'=>'2026-08-18','supplier'=>$linkedPartner->name,
            'warehouse'=>'Almacén principal','received_by'=>$admin->id,
        ]);

        $this->actingAs($admin)->delete(route('business-partners.destroy', $linkedPartner))->assertSessionHasErrors();
        $this->actingAs($admin)->delete(route('business-partners.destroy', $freePartner))->assertRedirect();
        $this->assertDatabaseHas('business_partners', ['id'=>$linkedPartner->id]);
        $this->assertDatabaseMissing('business_partners', ['id'=>$freePartner->id]);
    }

    public function test_a_reception_is_deleted_only_when_its_stock_movement_can_be_reverted(): void
    {
        $admin = $this->admin();
        $product = $this->product('PRD-STOCK', 15);
        $first = ProductReception::create(['code'=>'GRC 001 - 001','received_at'=>'2026-08-17','warehouse'=>'Almacén principal','received_by'=>$admin->id]);
        $first->items()->create(['product_id'=>$product->id,'product_code'=>$product->code,'product_name'=>$product->name,'unit'=>'Unidad','quantity'=>5,'stock_before'=>0,'stock_after'=>5]);
        $last = ProductReception::create(['code'=>'GRC 001 - 002','received_at'=>'2026-08-18','warehouse'=>'Almacén principal','received_by'=>$admin->id]);
        $last->items()->create(['product_id'=>$product->id,'product_code'=>$product->code,'product_name'=>$product->name,'unit'=>'Unidad','quantity'=>10,'stock_before'=>5,'stock_after'=>15]);

        $this->actingAs($admin)->delete(route('product-receptions.destroy', $first))->assertSessionHasErrors();
        $this->assertDatabaseHas('product_receptions', ['id'=>$first->id]);
        $this->assertSame(15, (int) $product->fresh()->stock);

        $this->actingAs($admin)->delete(route('product-receptions.destroy', $last))->assertRedirect();
        $this->assertDatabaseMissing('product_receptions', ['id'=>$last->id]);
        $this->assertSame(5, (int) $product->fresh()->stock);
    }

    public function test_pending_requirements_can_be_deleted_and_approvals_can_be_removed(): void
    {
        $admin = $this->admin();
        $pending = Requirement::create(['code'=>'REQ-2026-0001','requested_at'=>'2026-08-18','responsible'=>'Javier','project'=>'Mina','area'=>'Operaciones','priority'=>'Media','status'=>'Pendiente']);
        $approved = Requirement::create(['code'=>'REQ-2026-0002','requested_at'=>'2026-08-18','responsible'=>'Javier','project'=>'Mina','area'=>'Operaciones','priority'=>'Alta','status'=>'Aprobado','decision_at'=>now(),'decision_by'=>$admin->id]);

        $this->actingAs($admin)->delete(route('requirements.destroy', $pending))->assertRedirect();
        $this->assertDatabaseMissing('requirements', ['id'=>$pending->id]);
        $this->actingAs($admin)->delete(route('requirements.destroy', $approved))->assertSessionHasErrors();
        $this->assertDatabaseHas('requirements', ['id'=>$approved->id,'status'=>'Aprobado']);

        $this->actingAs($admin)->delete(route('approvals.destroy', $approved))->assertRedirect();
        $this->assertDatabaseHas('requirements', ['id'=>$approved->id,'status'=>'Pendiente','decision_by'=>null]);
        $this->assertNull($approved->fresh()->decision_at);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'profile'=>'Administrador',
            'permissions'=>['products','requirements','approvals','logistics','users'],
        ]);
    }

    private function product(string $code, int $stock = 0): Product
    {
        return Product::create([
            'code'=>$code,'warehouse'=>'Almacén principal','name'=>'Producto '.$code,'type'=>'Producto',
            'category'=>'Pruebas','unit'=>'Unidad','currency'=>'PEN','stock'=>$stock,'min_stock'=>0,
            'price'=>0,'includes_tax'=>true,'tax_affectation'=>'Gravado - Operación onerosa','is_active'=>true,
        ]);
    }

    private function partner(string $document, string $name): BusinessPartner
    {
        return BusinessPartner::create([
            'type'=>'Proveedor','document_type'=>'RUC','document_number'=>$document,
            'name'=>$name,'is_active'=>true,
        ]);
    }
}
