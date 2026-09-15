<?php
namespace Tests\Feature;
use App\Models\BusinessPartner;use App\Models\QuotationProcess;use App\Models\Requirement;use App\Models\User;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Http\UploadedFile;use Illuminate\Support\Facades\Storage;use Tests\TestCase;
class QuotationApprovalWorkflowTest extends TestCase{
 use RefreshDatabase;
 public function test_three_quotes_winner_rejection_resubmission_and_approval_flow():void{
  Storage::fake('local');$logistics=User::factory()->create(['permissions'=>['logistics']]);$approver=User::factory()->create(['permissions'=>['approvals']]);
  $requirement=Requirement::create(['code'=>'REQ-COT-001','requested_at'=>'2026-09-01','responsible'=>'Javier','project'=>'Mina','area'=>'Operaciones','priority'=>'Alta','status'=>'Aprobado total']);$requirement->items()->create(['product_name'=>'Bomba','quantity'=>1,'approved_quantity'=>1,'unit'=>'Unidad','approval_status'=>'Aprobado']);
  $suppliers=collect(range(1,3))->map(fn($i)=>BusinessPartner::create(['type'=>'Proveedor','document_type'=>'RUC','document_number'=>'2012345678'.$i,'name'=>'Proveedor '.$i,'is_active'=>true]));
  $this->actingAs($logistics)->get(route('quotations.index'))->assertOk()->assertSee('Contenido del requerimiento')->assertSee('Bomba')->assertSee('Cantidad aprobada')->assertSee('Ganador')->assertSee('Crear y seleccionar')->assertSee('Abrir Clientes y Proveedores');
  $two=$this->payload($suppliers->take(2));$this->actingAs($logistics)->post(route('quotations.store',$requirement),$two)->assertSessionHasErrors('quotes');
  $this->actingAs($logistics)->post(route('quotations.store',$requirement),$this->payload($suppliers,1))->assertRedirect(route('quotations.index'));
  $process=QuotationProcess::with('quotations')->firstOrFail();$this->assertSame('Pendiente aprobación',$process->status);$this->assertCount(3,$process->quotations);$this->assertSame($suppliers[1]->id,$process->winner->supplier_id);
  $this->actingAs($approver)->get(route('approvals.index',['seccion'=>'cotizaciones']))->assertOk()->assertSee('REQ-COT-001')->assertSee('Proveedor 2')->assertSee('GANADORA');
  $this->actingAs($approver)->post(route('approvals.quotations.decide',$process),['status'=>'Rechazada','observation'=>'Revisar plazo de entrega'])->assertRedirect();
  $this->assertDatabaseHas('quotation_processes',['id'=>$process->id,'status'=>'Rechazada','approval_observation'=>'Revisar plazo de entrega']);
  $this->actingAs($logistics)->get(route('quotations.index'))->assertSee('Revisar plazo de entrega')->assertSee('Volver a cotizar');
  $this->actingAs($logistics)->post(route('quotations.store',$requirement),$this->payload($suppliers,2))->assertRedirect();
  $this->actingAs($approver)->post(route('approvals.quotations.decide',$process->fresh()),['status'=>'Aprobada'])->assertRedirect();
  $this->assertDatabaseHas('quotation_processes',['id'=>$process->id,'status'=>'Aprobada','version'=>2]);
  $this->actingAs($logistics)->get(route('purchase-orders.index'))->assertOk()->assertSee('REQ-COT-001')->assertSee('Proveedor 3');
 }
 private function payload($suppliers,$winner=0):array{$quotes=[];foreach($suppliers as $i=>$supplier)$quotes[]=['supplier_label'=>$supplier->document_number.' · '.$supplier->name,'file'=>UploadedFile::fake()->create("quote-$i.pdf",20,'application/pdf'),'amount'=>100+$i,'currency'=>'PEN'];return ['quotes'=>$quotes,'winner_index'=>$winner];}
}
