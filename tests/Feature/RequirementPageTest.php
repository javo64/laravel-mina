<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Product;
use App\Models\Project;
use App\Models\Responsible;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_requirement_page_renders_for_an_authorized_user(): void
    {
        $user = User::factory()->create(['permissions' => ['requirements']]);
        Responsible::create(['name' => 'RESPONSABLE PRUEBA', 'is_active' => true]);
        Project::create(['name' => 'PROYECTO PRUEBA', 'is_active' => true]);
        Area::create(['name' => 'LOGISTICA', 'is_active' => true]);
        Product::create([
            'code' => 'PRD-REQ-01', 'name' => 'PRODUCTO PRUEBA', 'secondary_name' => 'PRUEBA',
            'description' => 'PRODUCTO PARA PRUEBA', 'type' => 'Producto', 'operation_type' => 'Venta',
            'category' => 'GENERAL', 'unit' => 'UND', 'currency' => 'PEN', 'warehouse' => 'Almacén principal',
            'barcode' => 'REQ-001', 'stock' => 0, 'min_stock' => 0, 'price' => 0,
            'tax_affectation' => 'Gravado', 'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('requirements.index'))
            ->assertOk()
            ->assertSee('Requerimientos')
            ->assertSee('Nuevo requerimiento')
            ->assertSee('PRODUCTO PRUEBA');
    }
}
