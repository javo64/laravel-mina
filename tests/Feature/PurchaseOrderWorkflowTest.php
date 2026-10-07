<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessPartner;
use App\Models\Requirement;
use App\Models\QuotationProcess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_user_can_create_an_order_only_from_an_approved_item(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['permissions' => ['logistics']]);
        $supplier = BusinessPartner::create(['type'=>'Proveedor', 'document_type'=>'RUC', 'document_number'=>'20123456789', 'name'=>'Proveedor SAC', 'is_active'=>true]);
        BusinessPartner::create(['type'=>'Cliente', 'document_type'=>'DNI', 'document_number'=>'12345678', 'name'=>'Cliente también disponible', 'is_active'=>true]);
        $account = BankAccount::create(['business_partner_id'=>$supplier->id, 'account_type'=>'Cuenta Corriente', 'account_number'=>'0011223344', 'bank_name'=>'Banco de Prueba', 'currency'=>'PEN', 'is_active'=>true]);
        $requirement = Requirement::create(['code'=>'REQ-OC-001', 'requested_at'=>'2026-08-20', 'responsible'=>'Javier', 'project'=>'Fabulosa', 'area'=>'LOGISTICA', 'priority'=>'Media', 'status'=>'Aprobado']);
        $item = $requirement->items()->create(['product_name'=>'Válvula', 'quantity'=>2, 'unit'=>'Unidad', 'priority'=>'Media', 'approval_status'=>'Aprobado']);
        Storage::disk('local')->put('requirement-quotations/winner.pdf','pdf');
        $process=QuotationProcess::create(['requirement_id'=>$requirement->id,'status'=>'Aprobada','submitted_at'=>now()]);
        $process->items()->attach($item->id);
        $winning=$process->quotations()->create(['supplier_id'=>$supplier->id,'path'=>'requirement-quotations/winner.pdf','original_name'=>'cotizacion-proveedor.pdf','mime_type'=>'application/pdf','size'=>3,'currency'=>'PEN','is_winner'=>true]);

        $this->actingAs($user)->get(route('purchase-orders.index'))
            ->assertOk()->assertSee('Órdenes de compra')->assertSee('REQ-OC-001')->assertSee('Seleccionar productos aprobados')
            ->assertSee('OCO: 001/002 · OS: 003/004')->assertSee('Cotización ganadora aprobada')
            ->assertSee('Proveedor SAC')->assertSee('Cliente también disponible')->assertSee('0011223344')->assertSee('Sincronizado con Clientes y Proveedores');

        $this->actingAs($user)->post(route('purchase-orders.store'), [
            'destination_branch'=>'Sucursal principal', 'destination_warehouse'=>'Almacén principal', 'document'=>'OCO', 'series'=>'001',
            'supplier_id'=>$supplier->id, 'bank_account_id'=>$account->id, 'payment_condition'=>'001 CONTADO', 'currency'=>'PEN', 'area'=>'LOGISTICA',
            'items'=>[['requirement_item_id'=>$item->id, 'cost_center'=>'CC-001', 'quantity'=>2, 'unit_price'=>100]],
        ])->assertRedirect(route('purchase-orders.index'));

        $this->assertDatabaseHas('purchase_orders', ['document'=>'OCO', 'series'=>'001', 'number'=>'000001', 'subtotal'=>200, 'tax'=>36, 'total'=>236]);
        $this->assertDatabaseHas('purchase_order_items', ['requirement_item_id'=>$item->id, 'cost_center'=>'CC-001', 'total'=>200]);
        $quotation = \App\Models\PurchaseOrderQuotation::firstOrFail();
        $this->assertSame('cotizacion-proveedor.pdf', $quotation->original_name);
        $this->assertSame($winning->id,$quotation->requirement_quotation_id);
        Storage::disk('local')->assertExists($quotation->path);
        $this->actingAs($user)->get(route('purchase-orders.quotations.show', $quotation))->assertOk()->assertHeader('content-type', 'application/pdf');
        $approver = User::factory()->create(['permissions'=>['approvals']]);
        $this->actingAs($approver)->get(route('purchase-orders.quotations.show', $quotation))->assertOk();
        $this->actingAs($approver)->get(route('approvals.index', ['seccion'=>'ordenes']))
            ->assertOk()->assertSee('cotizacion-proveedor.pdf')->assertSee('Doble clic')
            ->assertSee('purchase-order-review-'.$quotation->purchase_order_id)
            ->assertSee('PDF de la orden')->assertSee('PDF cotización ganadora')
            ->assertSee(route('purchase-orders.pdf', $quotation->purchaseOrder))
            ->assertSee(route('purchase-orders.quotations.show',$quotation));
        $this->actingAs($approver)->get(route('purchase-orders.pdf', $quotation->purchaseOrder))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)->get(route('purchase-orders.index'))->assertOk()->assertDontSee('REQ-OC-001');
        $this->actingAs($user)->post(route('purchase-orders.store'), [
            'destination_branch'=>'Sucursal principal', 'destination_warehouse'=>'Almacén principal', 'document'=>'OCO', 'series'=>'002',
            'supplier_id'=>$supplier->id, 'bank_account_id'=>$account->id, 'payment_condition'=>'001 CONTADO', 'currency'=>'PEN', 'area'=>'LOGISTICA',
            'items'=>[['requirement_item_id'=>$item->id, 'cost_center'=>'CC-001', 'quantity'=>2, 'unit_price'=>100]],
        ])->assertStatus(422);

        $this->actingAs($user)->get(route('purchase-orders.next-correlative', ['document' => 'OCO', 'series' => '001']))
            ->assertOk()->assertJsonPath('number', '000002');
        $order = \App\Models\PurchaseOrder::firstOrFail();
        $this->actingAs($user)->get(route('purchase-orders.pdf', $order))->assertOk()
            ->assertHeader('content-type', 'application/pdf')->assertSee('FABULOSA', false);
    }
}
