<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_visto_bueno_is_required_before_final_approval(): void
    {
        $reviewer = User::factory()->create(['permissions' => ['warehouse.approvals.review']]);
        $approver = User::factory()->create(['permissions' => ['warehouse.approvals.approve']]);
        [$requirement, $item] = $this->requirementWithItem();

        $this->actingAs($approver)
            ->post(route('approvals.items.decide', $item), ['status' => 'Aprobado'])
            ->assertSessionHasErrors();
        $this->assertSame('Pendiente', $item->fresh()->approval_status);

        $this->actingAs($reviewer)
            ->post(route('approvals.items.decide', $item), ['status' => 'Visto Bueno'])
            ->assertRedirect();
        $item->refresh();
        $requirement->refresh();
        $this->assertSame('Visto Bueno', $item->approval_status);
        $this->assertSame($reviewer->id, $item->reviewed_by);
        $this->assertSame('Visto Bueno', $requirement->status);

        $this->actingAs($reviewer)
            ->post(route('approvals.items.decide', $item), ['status' => 'Aprobado'])
            ->assertForbidden();

        $this->actingAs($approver)
            ->post(route('approvals.items.decide', $item), ['status' => 'Aprobado'])
            ->assertRedirect();
        $item->refresh();
        $requirement->refresh();
        $this->assertSame('Aprobado', $item->approval_status);
        $this->assertSame($approver->id, $item->decision_by);
        $this->assertSame('Aprobación', $requirement->status);
    }

    public function test_user_with_both_permissions_can_complete_both_stages(): void
    {
        $user = User::factory()->create(['permissions' => ['warehouse.approvals.review', 'warehouse.approvals.approve']]);
        [$requirement] = $this->requirementWithItem();

        $this->actingAs($user)
            ->post(route('approvals.decide', $requirement), ['status' => 'Visto Bueno'])
            ->assertRedirect();
        $this->assertSame('Visto Bueno', $requirement->fresh()->status);

        $this->actingAs($user)
            ->post(route('approvals.decide', $requirement), ['status' => 'Aprobado'])
            ->assertRedirect();
        $this->assertSame('Aprobación', $requirement->fresh()->status);
        $this->assertSame('Aprobado', $requirement->items()->firstOrFail()->approval_status);
    }

    public function test_reviewer_can_render_the_requirement_review_dialog_with_visto_bueno_action(): void
    {
        $reviewer = User::factory()->create(['permissions' => ['warehouse.approvals.review']]);
        [$requirement] = $this->requirementWithItem();

        $this->actingAs($reviewer)
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('approval-review-'.$requirement->id, false)
            ->assertSee('Visto Bueno total');
    }

    private function requirementWithItem(): array
    {
        $product = Product::create([
            'code' => 'PRD-APR-'.Product::count(), 'name' => 'Producto de aprobación', 'type' => 'Producto',
            'category' => 'Pruebas', 'unit' => 'Unidad', 'currency' => 'PEN', 'stock' => 0, 'min_stock' => 0,
            'price' => 10, 'includes_tax' => true, 'is_active' => true,
        ]);
        $requirement = Requirement::create([
            'code' => 'REQ-APR-'.str_pad((string) (Requirement::count() + 1), 4, '0', STR_PAD_LEFT),
            'requested_at' => now()->toDateString(), 'responsible' => 'Solicitante', 'project' => 'Proyecto',
            'area' => 'Área', 'priority' => 'Media', 'status' => 'Pendiente',
        ]);
        $item = $requirement->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 2,
            'unit' => 'Unidad', 'priority' => 'Media', 'approval_status' => 'Pendiente',
        ]);

        return [$requirement, $item];
    }
}
