<?php

namespace Tests\Feature;

use App\Models\Requirement;
use App\Models\RequirementItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalItemWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_item_can_be_decided_independently_and_requirement_becomes_partial(): void
    {
        $approver = User::factory()->create(['permissions'=>['approvals']]);
        $requirement = $this->requirement();
        $first = $requirement->items()->create(['product_name'=>'Filtro','quantity'=>2,'unit'=>'Unidad','priority'=>'Alta']);
        $second = $requirement->items()->create(['product_name'=>'Aceite','quantity'=>5,'unit'=>'Galón','priority'=>'Media']);

        $this->actingAs($approver)->post(route('approvals.items.decide', $first), ['status'=>'Aprobado'])
            ->assertRedirect(route('approvals.index', ['estado'=>'Aprobado']));

        $this->assertDatabaseHas('requirement_items', ['id'=>$first->id,'approval_status'=>'Aprobado','decision_by'=>$approver->id]);
        $this->assertDatabaseHas('requirement_items', ['id'=>$second->id,'approval_status'=>'Pendiente','decision_by'=>null]);
        $this->assertSame('Parcial', $requirement->fresh()->status);
    }

    public function test_tabs_separate_pending_approved_rejected_and_annulled_items(): void
    {
        $approver = User::factory()->create(['permissions'=>['approvals']]);
        foreach (['Pendiente','Aprobado','Rechazado','Anulado'] as $index=>$status) {
            $requirement = Requirement::create([
                'code'=>'REQ-TAB-00'.($index+1),'requested_at'=>'2026-08-19','responsible'=>'Javier',
                'project'=>'Fabulosa','area'=>'Operaciones','priority'=>'Alta','status'=>$status,
            ]);
            $requirement->items()->create([
                'product_name'=>'Producto '.$status,'quantity'=>1,'unit'=>'Unidad','priority'=>'Media',
                'approval_status'=>$status,
            ]);
        }

        foreach (['Pendiente','Aprobado','Rechazado','Anulado'] as $status) {
            $response = $this->actingAs($approver)->get(route('approvals.index', ['estado'=>$status]));
            $response->assertOk()->assertSee('Producto '.$status);
            foreach (array_diff(['Pendiente','Aprobado','Rechazado','Anulado'], [$status]) as $other) {
                $response->assertDontSee('Producto '.$other);
            }
        }
    }

    public function test_bulk_decision_keeps_legacy_requirement_action_compatible(): void
    {
        $approver = User::factory()->create(['permissions'=>['approvals']]);
        $requirement = $this->requirement();
        $requirement->items()->createMany([
            ['product_name'=>'Filtro','quantity'=>2,'unit'=>'Unidad','priority'=>'Alta'],
            ['product_name'=>'Aceite','quantity'=>5,'unit'=>'Galón','priority'=>'Media'],
        ]);

        $this->actingAs($approver)->post(route('approvals.decide', $requirement), ['status'=>'Rechazado'])->assertRedirect();

        $this->assertSame('Rechazado', $requirement->fresh()->status);
        $this->assertSame(2, RequirementItem::where('requirement_id', $requirement->id)->where('approval_status', 'Rechazado')->count());
    }

    public function test_administrator_sees_configuration_even_with_incomplete_permissions(): void
    {
        $admin = User::factory()->create(['profile'=>'Administrador','permissions'=>['approvals']]);

        $this->actingAs($admin)->get(route('approvals.index'))->assertOk()
            ->assertSee('Configuración OpenAI')->assertSee('API de documentos');
        $this->actingAs($admin)->get(route('settings.openai.edit'))->assertOk();
        $this->actingAs($admin)->get(route('settings.document-api.edit'))->assertOk();
    }

    private function requirement(): Requirement
    {
        return Requirement::create([
            'code'=>'REQ-ITEM-001','requested_at'=>'2026-08-19','responsible'=>'Javier',
            'project'=>'Fabulosa','area'=>'Operaciones','priority'=>'Alta','status'=>'Pendiente',
        ]);
    }
}
