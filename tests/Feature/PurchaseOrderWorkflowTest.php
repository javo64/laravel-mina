<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessPartner;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_user_can_create_an_order_only_from_an_approved_item(): void
    {
        $user = User::factory()->create(['permissions' => ['logistics']]);
        $supplier = BusinessPartner::create(['type'=>'Proveedor', 'document_type'=>'RUC', 'document_number'=>'20123456789', 'name'=>'Proveedor SAC', 'is_active'=>true]);
        $account = BankAccount::create(['business_partner_id'=>$supplier->id, 'account_type'=>'Cuenta Corriente', 'account_number'=>'0011223344', 'bank_name'=>'Banco de Prueba', 'currency'=>'PEN', 'is_active'=>true]);
        $requirement = Requirement::create(['code'=>'REQ-OC-001', 'requested_at'=>'2026-08-20', 'responsible'=>'Javier', 'project'=>'Fabulosa', 'area'=>'LOGISTICA', 'priority'=>'Media', 'status'=>'Aprobado']);
        $item = $requirement->items()->create(['product_name'=>'Válvula', 'quantity'=>2, 'unit'=>'Unidad', 'priority'=>'Media', 'approval_status'=>'Aprobado']);

        $this->actingAs($user)->post(route('purchase-orders.store'), [
            'destination_branch'=>'Sucursal principal', 'destination_warehouse'=>'Almacén principal', 'document'=>'OCO', 'series'=>'001', 'number'=>'000001',
            'supplier_id'=>$supplier->id, 'bank_account_id'=>$account->id, 'payment_condition'=>'001 CONTADO', 'currency'=>'PEN', 'area'=>'LOGISTICA',
            'items'=>[['requirement_item_id'=>$item->id, 'cost_center'=>'CC-001', 'quantity'=>2, 'unit_price'=>100]],
        ])->assertRedirect(route('purchase-orders.index'));

        $this->assertDatabaseHas('purchase_orders', ['document'=>'OCO', 'series'=>'001', 'number'=>'000001', 'subtotal'=>200, 'tax'=>36, 'total'=>236]);
        $this->assertDatabaseHas('purchase_order_items', ['requirement_item_id'=>$item->id, 'cost_center'=>'CC-001', 'total'=>200]);
    }
}
