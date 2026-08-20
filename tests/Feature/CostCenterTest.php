<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_costs_user_sees_default_groups_and_can_create_children(): void
    {
        $user = User::factory()->create(['permissions' => ['costs']]);

        $this->actingAs($user)->get(route('cost-centers.index'))
            ->assertOk()
            ->assertSee('MAQUINARIAS Y VEHICULOS')
            ->assertSee('GASTOS ADMINISTRATIVOS')
            ->assertSee('GASTOS FINANCIEROS')
            ->assertSee('GASTOS OPERACIONES');

        $parent = CostCenter::where('name', 'GASTOS OPERACIONES')->firstOrFail();
        $this->actingAs($user)->post(route('cost-centers.store'), [
            'parent_id' => $parent->id,
            'name' => 'MANTENIMIENTO DE PLANTA',
            'description' => 'Costos de mantenimiento operativo.',
        ])->assertRedirect(route('cost-centers.index'));

        $this->assertDatabaseHas('cost_centers', ['parent_id' => $parent->id, 'name' => 'MANTENIMIENTO DE PLANTA']);
    }

    public function test_user_without_costs_permission_cannot_manage_cost_centers(): void
    {
        $user = User::factory()->create(['permissions' => ['logistics']]);

        $this->actingAs($user)->get(route('cost-centers.index'))->assertForbidden();
    }
}
