<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_page_contains_full_review_dialog_and_pdf_preview(): void
    {
        $approver = User::factory()->create([
            'profile'=>'Administrador','permissions'=>['approvals'],
        ]);
        $product = Product::create([
            'code'=>'PRD-PDF-01','warehouse'=>'Almacén principal','name'=>'Filtro hidráulico',
            'type'=>'Producto','category'=>'Repuestos','unit'=>'Unidad','currency'=>'PEN',
            'stock'=>0,'min_stock'=>0,'price'=>0,'includes_tax'=>true,
            'tax_affectation'=>'Gravado - Operación onerosa','is_active'=>true,
        ]);
        $requirement = Requirement::create([
            'code'=>'REQ-2026-0099','requested_at'=>'2026-08-18','responsible'=>'Javier Alcántara',
            'project'=>'Mina Fabulosa','area'=>'Mantenimiento','priority'=>'Alta','status'=>'Pendiente',
        ]);
        $requirement->items()->create([
            'product_id'=>$product->id,'product_name'=>$product->name,
            'description'=>'Filtro para el generador principal','quantity'=>12,'unit'=>'Unidad','priority'=>'Alta',
        ]);

        $this->actingAs($approver)->get(route('approvals.index'))->assertOk()
            ->assertSee('Doble clic')->assertSee('approval-review-'.$requirement->id)
            ->assertSee('Filtro hidráulico')->assertSee(route('approvals.pdf', $requirement))
            ->assertSee('Descargar PDF')->assertSee('sidebar-collapse')
            ->assertSee('Contraer menú')->assertSee('data-label="Aprobaciones"', false);

        $pdf = $this->actingAs($approver)->get(route('approvals.pdf', $requirement));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="requerimiento-REQ-2026-0099.pdf"');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringContainsString('REQ-2026-0099', $pdf->getContent());
        $this->assertStringContainsString(iconv('UTF-8', 'Windows-1252', 'Filtro hidráulico'), $pdf->getContent());

        $this->actingAs($approver)->get(route('approvals.pdf', [$requirement,'download'=>1]))
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename="requerimiento-REQ-2026-0099.pdf"');
    }
}
