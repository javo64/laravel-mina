<?php
namespace Tests\Feature;
use App\Models\BusinessPartner;use App\Models\QuotationProcess;use App\Models\Requirement;use App\Models\User;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Http\UploadedFile;use Illuminate\Support\Facades\Storage;use Tests\TestCase;
class QuotationApprovalWorkflowTest extends TestCase{
 use RefreshDatabase;
 public function test_three_quotes_winner_rejection_resubmission_and_approval_flow():void{
  Storage::fake('local');$logistics=User::factory()->create(['permissions'=>['logistics']]);$approver=User::factory()->create(['permissions'=>['approvals']]);
  $requirement=Requirement::create(['code'=>'REQ-COT-001','requested_at'=>'2026-09-01','responsible'=>'Javier','project'=>'Mina','area'=>'Operaciones','priority'=>'Alta','status'=>'Aprobado total']);$requirement->items()->create(['product_name'=>'Bomba','quantity'=>1,'approved_quantity'=>1,'unit'=>'Unidad','approval_status'=>'Aprobado']);
  $suppliers=collect(range(1,3))->map(fn($i)=>BusinessPartner::create(['type'=>'Proveedor','document_type'=>'RUC','document_number'=>'2012345678'.$i,'name'=>'Proveedor '.$i,'is_active'=>true]));
  $this->actingAs($logistics)->get(route('quotations.index'))->assertOk()->assertSee('Ítems aprobados para cotizar')->assertSee('Bomba')->assertSee('Cantidad aprobada')->assertSee('Ganador')->assertSee('Crear bloque de cotización');
  $two=$this->payload($suppliers->take(2));$this->actingAs($logistics)->post(route('quotations.store',$requirement),$two)->assertSessionHasErrors('quotes');
  $this->actingAs($logistics)->post(route('quotations.store',$requirement),$this->payload($suppliers,1))->assertRedirect(route('quotations.index'));
  $process=QuotationProcess::with('quotations')->firstOrFail();$this->assertSame('Pendiente aprobación',$process->status);$this->assertCount(3,$process->quotations);$this->assertSame($suppliers[1]->id,$process->winner->supplier_id);
  $this->actingAs($approver)->get(route('approvals.index',['seccion'=>'cotizaciones']))->assertOk()->assertSee('REQ-COT-001')->assertSee('Proveedor 2')->assertSee('GANADORA');
  $this->actingAs($approver)->post(route('approvals.quotations.decide',$process),['status'=>'Rechazada','observation'=>'Revisar plazo de entrega'])->assertRedirect();
  $this->assertDatabaseHas('quotation_processes',['id'=>$process->id,'status'=>'Rechazada','approval_observation'=>'Revisar plazo de entrega']);
  $this->actingAs($logistics)->get(route('quotations.index'))->assertSee('Revisar plazo de entrega')->assertSee('Volver a cotizar');
  $this->actingAs($logistics)->post(route('quotations.store',$requirement),$this->payload($suppliers,2))->assertRedirect();
  $resubmitted=QuotationProcess::latest('id')->firstOrFail();
  $this->assertNotSame($process->id,$resubmitted->id);
  $this->actingAs($approver)->post(route('approvals.quotations.decide',$resubmitted),['status'=>'Aprobada'])->assertRedirect();
  $this->assertDatabaseHas('quotation_processes',['id'=>$resubmitted->id,'status'=>'Aprobada']);
  $this->actingAs($logistics)->get(route('purchase-orders.index'))->assertOk()->assertSee('REQ-COT-001')->assertSee('Proveedor 3');
 }
 public function test_can_quote_a_block_with_items_from_several_finally_approved_requirements():void{
  Storage::fake('local');$logistics=User::factory()->create(['permissions'=>['logistics']]);
  $first=Requirement::create(['code'=>'REQ-COT-B01','requested_at'=>'2026-09-01','responsible'=>'Javier','project'=>'Mina','area'=>'Operaciones','priority'=>'Alta','status'=>'Aprobación']);
  $second=Requirement::create(['code'=>'REQ-COT-B02','requested_at'=>'2026-09-02','responsible'=>'Javier','project'=>'Mina','area'=>'Operaciones','priority'=>'Alta','status'=>'Aprobación']);
  $firstItem=$first->items()->create(['product_name'=>'Manguera','quantity'=>2,'approved_quantity'=>2,'unit'=>'Unidad','approval_status'=>'Aprobado']);
  $secondItem=$second->items()->create(['product_name'=>'Filtro','quantity'=>3,'approved_quantity'=>3,'unit'=>'Unidad','approval_status'=>'Aprobado parcial']);
  $pending=$second->items()->create(['product_name'=>'Pendiente','quantity'=>1,'unit'=>'Unidad','approval_status'=>'Pendiente']);
  $suppliers=collect(range(1,3))->map(fn($i)=>BusinessPartner::create(['type'=>'Proveedor','document_type'=>'RUC','document_number'=>'2098765432'.$i,'name'=>'Proveedor Bloque '.$i,'is_active'=>true]));
  $this->actingAs($logistics)->get(route('quotations.index'))->assertOk()->assertSee('REQ-COT-B01')->assertSee('REQ-COT-B02')->assertDontSee('Pendiente');
  $this->actingAs($logistics)->post(route('quotations.blocks.store'),$this->payload($suppliers,0)+['item_ids'=>[$firstItem->id,$secondItem->id]])->assertRedirect(route('quotations.index'));
  $process=QuotationProcess::with('items')->firstOrFail();
  $this->assertNotEmpty($process->block_code);$this->assertSame([$firstItem->id,$secondItem->id],$process->items->pluck('id')->sort()->values()->all());
  $this->assertDatabaseMissing('quotation_process_items',['quotation_process_id'=>$process->id,'requirement_item_id'=>$pending->id]);
 }
 private function payload($suppliers,$winner=0):array{$quotes=[];foreach($suppliers as $i=>$supplier)$quotes[]=['supplier_label'=>$supplier->document_number.' · '.$supplier->name,'file'=>UploadedFile::fake()->create("quote-$i.pdf",20,'application/pdf'),'amount'=>100+$i,'currency'=>'PEN'];return ['quotes'=>$quotes,'winner_index'=>$winner];}
}
