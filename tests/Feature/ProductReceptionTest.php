<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Models\BusinessPartner;
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
        BusinessPartner::create([
            'type' => 'Proveedor',
            'document_type' => 'RUC',
            'document_number' => '20123456789',
            'name' => 'Proveedor de prueba',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('product-receptions.store'), [
            'received_at' => '2026-07-24',
            'supplier' => 'Proveedor de prueba',
            'document_reference' => 'GUIA-001',
            'warehouse' => 'Almacén principal',
            'items' => [['product_id' => $product->id, 'quantity' => 7]],
        ]);

        $response->assertRedirect(route('product-receptions.index'));
        $this->assertSame(17, (int) $product->fresh()->stock);
        $item = \App\Models\ProductReceptionItem::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(7, (int) $item->quantity);
        $this->assertSame(10, (int) $item->stock_before);
        $this->assertSame(17, (int) $item->stock_after);
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

        $response = $this->actingAs($user)->from(route('product-receptions.index'))
            ->post(route('product-receptions.store'), [
                'received_at' => '2026-07-24',
                'warehouse' => 'Almacén principal',
                'items' => [['product_id' => $service->id, 'quantity' => 1]],
            ]);

        $response->assertRedirect(route('product-receptions.index'));
        $response->assertSessionHasErrors('items.0.product_id');
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

        $response = $this->actingAs($user)->post(route('product-receptions.store'), [
            'received_at' => '2026-07-24',
            'warehouse' => 'Almacén principal',
            'guide_number' => 'GUIA-100',
            'guide_file' => UploadedFile::fake()->create('guia.pdf', 100, 'application/pdf'),
            'invoice_number' => 'F001-200',
            'invoice_file' => UploadedFile::fake()->create('factura.jpg', 100, 'image/jpeg'),
            'order_number' => 'OC-300',
            'order_file' => UploadedFile::fake()->create('orden.pdf', 100, 'application/pdf'),
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertRedirect(route('product-receptions.index'));
        $reception = \App\Models\ProductReception::firstOrFail();
        $this->assertSame('GRC 001 - 001', $reception->code);
        $this->assertSame('GUIA-100', $reception->guide_number);
        $this->assertSame('F001-200', $reception->invoice_number);
        $this->assertSame('OC-300', $reception->order_number);
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
        BusinessPartner::create([
            'type' => 'Proveedor', 'document_type' => 'RUC',
            'document_number' => '20111222333', 'name' => 'SUMINISTROS MINEROS SAC',
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('product-receptions.store'), [
            'received_at' => '2026-07-24', 'warehouse' => 'Almacén principal',
            'supplier' => 'SUMINISTROS MINEROS SAC',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertRedirect(route('product-receptions.index'));
        $this->assertDatabaseHas('product_receptions', ['supplier' => 'SUMINISTROS MINEROS SAC']);

        $this->actingAs($user)->from(route('product-receptions.index'))
            ->post(route('product-receptions.store'), [
                'received_at' => '2026-07-24', 'warehouse' => 'Almacén principal',
                'supplier' => 'PROVEEDOR NO REGISTRADO',
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])->assertSessionHasErrors('supplier');
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
            ->assertOk()->assertSee('supplier-suggestions')->assertDontSee('registered-suppliers');
    }
}
