<?php

namespace Tests\Feature;

use App\Models\DocumentApiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LogisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_user_can_lookup_ruc_and_save_supplier(): void
    {
        $user = User::factory()->create(['permissions' => ['logistics']]);
        DocumentApiSetting::create([
            'url' => 'https://documents.test',
            'token' => 'secret-document-token',
            'is_active' => true,
        ]);
        Http::fake(['documents.test/*' => Http::response(['data' => [
            'razonSocial' => 'PROVEEDOR MINERO SAC',
            'nombreComercial' => 'MINERO',
            'direccion' => 'AV. PRINCIPAL 123',
            'distrito' => 'LIMA',
            'provincia' => 'LIMA',
            'departamento' => 'LIMA',
        ]])]);

        $this->actingAs($user)->postJson(route('business-partners.lookup'), [
            'document_number' => '20123456789',
        ])->assertOk()->assertJsonPath('document_type', 'RUC')
            ->assertJsonPath('name', 'PROVEEDOR MINERO SAC');

        $this->actingAs($user)->post(route('business-partners.store'), [
            'type' => 'Proveedor', 'document_type' => 'RUC',
            'document_number' => '20123456789', 'name' => 'PROVEEDOR MINERO SAC',
            'trade_name' => 'MINERO', 'address' => 'AV. PRINCIPAL 123',
            'is_active' => '1',
        ])->assertRedirect(route('business-partners.index'));

        $this->assertDatabaseHas('business_partners', [
            'document_number' => '20123456789', 'type' => 'Proveedor',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://documents.test/api/ruc'
            && $request->method() === 'POST'
            && $request['ruc'] === '20123456789'
            && $request->hasHeader('Authorization', 'Bearer secret-document-token'));
    }

}
