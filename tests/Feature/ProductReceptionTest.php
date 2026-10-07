<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Models\BusinessPartner;
use App\Models\PurchaseOrder;
use App\Models\Requirement;
use App\Models\Warehouse;
use Tests\TestCase;

class ProductReceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reception_increases_product_stock_and_keeps_history(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);
        $product = Product::create([
            'code' => 'PRD-TEST-01',
            'warehouse' => 'Almacén principal',
            'name' => 'Producto de prueba',
            'type' => 'Producto',
            'category' => 'Pruebas',
            'unit' => 'Unidad',
            'currency' => 'PEN',
            'stock' => 10,
            'min_stock' => 1,
            'price' => 0,
            'includes_tax' => true,
            'tax_affectation' => 'Gravado - Operación onerosa',
            'is_active' => true,
        ]);
        $supplier = BusinessPartner::create([
            'type' => 'Proveedor',
            'document_type' => 'RUC',
            'document_number' => '20123456789',
            'name' => 'Proveedor de prueba',
            'is_active' => true,
        ]);
        [$order, $orderItem] = $this->approvedOrder($product, $supplier, 7);

        $response = $this->actingAs($user)->post(route('product-receptions.store'), [
            'received_at' => '2026-07-24',
            'purchase_order_id' => $order->id,
            'document_reference' => 'GUIA-001',
            'items' => [['purchase_order_item_id' => $orderItem->id, 'quantity' => 7]],
        ]);

        $response->assertRedirect(route('product-receptions.index'));
        $this->assertSame(17, (int) $product->fresh()->stock);
        $item = \App\Models\ProductReceptionItem::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(7, (int) $item->quantity);
        $this->assertSame(10, (int) $item->stock_before);
        $this->assertSame(17, (int) $item->stock_after);
        $this->assertSame('Completa', $order->fresh()->receipt_status);
    }

    public function test_services_cannot_be_received(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);
        $service = Product::create([
            'code' => 'SRV-TEST-01',
            'warehouse' => 'Almacén principal',
            'name' => 'Servicio de prueba',
            'type' => 'Servicio',
            'category' => 'Pruebas',
            'unit' => 'Servicio',
            'currency' => 'PEN',
            'stock' => 0,
            'min_stock' => 0,
            'price' => 0,
            'includes_tax' => true,
            'tax_affectation' => 'Gravado - Operación onerosa',
            'is_active' => true,
        ]);
        $supplier = BusinessPartner::create(['type'=>'Proveedor','document_type'=>'RUC','document_number'=>'20999999991','name'=>'Proveedor servicios','is_active'=>true]);
        [$order, $orderItem] = $this->approvedOrder($service, $supplier, 1);

        $response = $this->actingAs($user)->from(route('product-receptions.index'))
            ->post(route('product-receptions.store'), [
                'received_at' => '2026-07-24',
                'purchase_order_id' => $order->id,
                'items' => [['purchase_order_item_id' => $orderItem->id, 'quantity' => 1]],
            ]);

        $response->assertRedirect(route('product-receptions.index'));
        $response->assertSessionHasErrors('items.0.purchase_order_item_id');
        $this->assertDatabaseCount('product_receptions', 0);
        $this->assertDatabaseHas('products', ['id' => $service->id, 'stock' => 0]);
    }

    public function test_reception_stores_guide_invoice_and_order_documents(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['permissions' => ['products']]);
        $product = Product::create([
            'code' => 'PRD-DOC-01', 'warehouse' => 'Almacén principal',
            'name' => 'Producto con documentos', 'type' => 'Producto',
            'category' => 'Pruebas', 'unit' => 'Unidad', 'currency' => 'PEN',
            'stock' => 0, 'min_stock' => 0, 'price' => 0,
            'includes_tax' => true, 'tax_affectation' => 'Gravado - Operación onerosa',
            'is_active' => true,
        ]);
        $supplier = BusinessPartner::create(['type'=>'Proveedor','document_type'=>'RUC','document_number'=>'20999999992','name'=>'Proveedor documentos','is_active'=>true]);
        [$order, $orderItem] = $this->approvedOrder($product, $supplier, 2);

        $response = $this->actingAs($user)->post(route('product-receptions.store'), [
            'received_at' => '2026-07-24',
            'purchase_order_id' => $order->id,
            'guide_number' => 'GUIA-100',
            'guide_file' => UploadedFile::fake()->create('guia.pdf', 100, 'application/pdf'),
            'invoice_number' => 'F001-200',
            'invoice_file' => UploadedFile::fake()->create('factura.jpg', 100, 'image/jpeg'),
            'order_number' => 'OC-300',
            'order_file' => UploadedFile::fake()->create('orden.pdf', 100, 'application/pdf'),
            'items' => [['purchase_order_item_id' => $orderItem->id, 'quantity' => 2]],
        ]);

        $response->assertRedirect(route('product-receptions.index'));
        $reception = \App\Models\ProductReception::firstOrFail();
        $this->assertSame('GRC 001 - 001', $reception->code);
        $this->assertSame('GUIA-100', $reception->guide_number);
        $this->assertSame('F001-200', $reception->invoice_number);
        $this->assertSame($order->code, $reception->order_number);
        Storage::disk('public')->assertExists($reception->guide_file);
        Storage::disk('public')->assertExists($reception->invoice_file);
        Storage::disk('public')->assertExists($reception->order_file);
    }

    public function test_openai_analysis_returns_catalog_products_and_quantities(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);
        $product = Product::create([
            'code' => 'PRD-OCR-01', 'warehouse' => 'Almacén principal',
            'name' => 'Casco de seguridad', 'type' => 'Producto',
            'category' => 'EPP', 'unit' => 'Unidad', 'currency' => 'PEN',
            'stock' => 4, 'min_stock' => 1, 'price' => 0,
            'includes_tax' => true, 'tax_affectation' => 'Gravado - Operación onerosa',
            'is_active' => true,
        ]);
        \App\Models\OpenAiSetting::create([
            'api_key' => 'sk-proj-example-secret-key-1234567890',
            'model' => 'gpt-5.6-sol',
            'is_active' => true,
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'document_number' => 'G-001-25',
                            'items' => [[
                                'product_id' => $product->id,
                                'document_description' => 'Casco de seguridad',
                                'quantity' => 12,
                                'confidence' => 0.98,
                            ]],
                        ]),
                    ]],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson(route('product-receptions.analyze-document'), [
            'document' => UploadedFile::fake()->create('guia.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertOk()
            ->assertJsonPath('document_number', 'G-001-25')
            ->assertJsonPath('items.0.product_id', $product->id)
            ->assertJsonPath('items.0.quantity', 12)
            ->assertJsonPath('items.0.matched', true);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['model'] === 'gpt-5.6-sol');
    }

    public function test_reception_accepts_only_registered_active_suppliers(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);
        $product = Product::create([
            'code' => 'PRD-SUP-01', 'warehouse' => 'Almacén principal',
            'name' => 'Producto de proveedor', 'type' => 'Producto',
            'category' => 'Pruebas', 'unit' => 'Unidad', 'currency' => 'PEN',
            'stock' => 0, 'min_stock' => 0, 'price' => 0,
            'includes_tax' => true, 'tax_affectation' => 'Gravado - Operación onerosa',
            'is_active' => true,
        ]);
        $supplier = BusinessPartner::create([
            'type' => 'Proveedor', 'document_type' => 'RUC',
            'document_number' => '20111222333', 'name' => 'SUMINISTROS MINEROS SAC',
            'is_active' => true,
        ]);
        [$order, $orderItem] = $this->approvedOrder($product, $supplier, 5);

        $this->actingAs($user)->post(route('product-receptions.store'), [
            'received_at' => '2026-07-24', 'purchase_order_id' => $order->id,
            'supplier' => 'PROVEEDOR ALTERADO',
            'items' => [['purchase_order_item_id' => $orderItem->id, 'quantity' => 3]],
        ])->assertRedirect(route('product-receptions.index'));
        $this->assertDatabaseHas('product_receptions', ['supplier' => 'SUMINISTROS MINEROS SAC']);
    }

    public function test_supplier_search_starts_with_two_characters_and_only_returns_matches(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);
        BusinessPartner::create([
            'type'=>'Proveedor','document_type'=>'RUC','document_number'=>'20111222333',
            'name'=>'SUMINISTROS MINEROS SAC','trade_name'=>'SUMIN','is_active'=>true,
        ]);
        BusinessPartner::create([
            'type'=>'Proveedor','document_type'=>'RUC','document_number'=>'20444555666',
            'name'=>'TRANSPORTES DEL SUR SAC','is_active'=>true,
        ]);

        $this->actingAs($user)->getJson(route('product-receptions.suppliers.search', ['q'=>'S']))
            ->assertOk()->assertExactJson([]);
        $this->actingAs($user)->getJson(route('product-receptions.suppliers.search', ['q'=>'MIN']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'SUMINISTROS MINEROS SAC');
        $this->actingAs($user)->get(route('product-receptions.index'))
            ->assertOk()->assertSee('Órdenes listas para recepción')->assertSee('Orden de compra aprobada');
    }

    public function test_partial_receptions_keep_the_pending_balance_until_the_order_is_complete(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);
        $product = Product::create(['code'=>'PRD-PARCIAL','warehouse'=>'Almacén principal','name'=>'Explosivo emulsión','type'=>'Producto','category'=>'Operación mina','unit'=>'Caja','currency'=>'PEN','stock'=>0,'min_stock'=>1,'price'=>25,'includes_tax'=>true,'tax_affectation'=>'Gravado','is_active'=>true]);
        $supplier = BusinessPartner::create(['type'=>'Proveedor','document_type'=>'RUC','document_number'=>'20999999993','name'=>'Proveedor minero','is_active'=>true]);
        [$order, $orderItem] = $this->approvedOrder($product, $supplier, 10);

        foreach ([4, 6] as $quantity) {
            $this->actingAs($user)->post(route('product-receptions.store'), [
                'purchase_order_id'=>$order->id, 'received_at'=>'2026-09-28',
                'items'=>[['purchase_order_item_id'=>$orderItem->id,'quantity'=>$quantity]],
            ])->assertRedirect(route('product-receptions.index'));
            if ($quantity === 4) {
                $this->assertSame('Parcial', $order->fresh()->receipt_status);
                $this->assertSame(4, (int) $orderItem->fresh()->received_quantity);
            }
        }

        $this->assertSame('Completa', $order->fresh()->receipt_status);
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertDatabaseCount('product_receptions', 2);
        $this->assertDatabaseHas('inventory_movements', ['type'=>'Entrada','product_id'=>$product->id,'quantity'=>6]);
    }

    private function approvedOrder(Product $product, BusinessPartner $supplier, float $quantity): array
    {
        $warehouse = Warehouse::with('branch')->firstOrFail();
        $requirement = Requirement::create(['code'=>'REQ-'.strtoupper(substr(md5(uniqid('', true)),0,8)),'requested_at'=>'2026-09-28','responsible'=>'Jefe de mina','project'=>'Mina Carolina','area'=>'LOGISTICA','priority'=>'Media','status'=>'Aprobado']);
        $requirementItem = $requirement->items()->create(['product_id'=>$product->id,'product_name'=>$product->name,'quantity'=>$quantity,'approved_quantity'=>$quantity,'unit'=>$product->unit,'priority'=>'Media','approval_status'=>'Aprobado']);
        $order = PurchaseOrder::create(['code'=>'OCO-'.strtoupper(substr(md5(uniqid('', true)),0,8)),'destination_branch'=>$warehouse->branch?->name ?? 'Sucursal principal','destination_warehouse'=>$warehouse->name,'document'=>'OCO','series'=>'001','number'=>(string) random_int(100000,999999),'supplier_id'=>$supplier->id,'payment_condition'=>'CONTADO','currency'=>'PEN','area'=>'LOGISTICA','subtotal'=>0,'tax'=>0,'total'=>0,'status'=>'Aprobada','receipt_status'=>'Pendiente']);
        $orderItem = $order->items()->create(['requirement_item_id'=>$requirementItem->id,'product_id'=>$product->id,'product_name'=>$product->name,'cost_center'=>'MINA','quantity'=>$quantity,'received_quantity'=>0,'unit'=>$product->unit,'unit_price'=>0,'total'=>0]);
        return [$order, $orderItem];
    }
}
