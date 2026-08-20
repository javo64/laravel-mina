<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CostCenter;
use App\Models\Product;
use App\Models\Project;
use App\Models\RequirementItem;
use App\Models\Responsible;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementCostCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_requirement_item_requires_and_saves_an_active_child_cost_center(): void
    {
        $user = User::factory()->create(['permissions' => ['requirements']]);
        $parent = CostCenter::create(['name' => 'GASTOS OPERACIONES', 'is_active' => true]);
        $center = CostCenter::create(['parent_id' => $parent->id, 'name' => 'MANTENIMIENTO', 'code' => 'GO-01', 'is_active' => true]);
        $product = Product::create(['code' => 'PR-001', 'name' => 'Filtro', 'unit' => 'Unidad', 'is_active' => true]);
        Responsible::create(['name' => 'Javier Alcántara', 'is_active' => true]);
        Area::create(['name' => 'OPERACIONES', 'is_active' => true]);
        Project::firstOrCreate(['name' => 'MINA CAROLINA JE'], ['code' => 'MCJ', 'is_active' => true]);

        $this->actingAs($user)->get(route('requirements.index'))->assertOk()
            ->assertSee('MINA CAROLINA JE')->assertSee('GASTOS OPERACIONES · MANTENIMIENTO')
            ->assertSee('Agregar nuevo responsable')->assertSee('Agregar nueva área');

        $this->actingAs($user)->post(route('requirements.store'), [
            'requested_at' => '2026-08-20', 'responsible' => 'Javier Alcántara',
            'project' => 'MINA CAROLINA JE', 'area' => 'OPERACIONES',
            'items' => [[
                'product_id' => $product->id, 'cost_center_id' => $center->id,
                'quantity' => 2, 'priority' => 'Media', 'description' => 'Filtro de prueba',
            ]],
        ])->assertRedirect(route('requirements.index'));

        $this->assertDatabaseHas('requirement_items', ['product_id' => $product->id, 'cost_center_id' => $center->id, 'cost_center' => 'MANTENIMIENTO']);
    }
}
