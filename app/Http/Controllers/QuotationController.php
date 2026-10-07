<?php

namespace App\Http\Controllers;

use App\Models\BusinessPartner;
use App\Models\QuotationProcess;
use App\Models\Requirement;
use App\Models\RequirementQuotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('logistics.quotations'), 403); }

    public function index()
    {
        $this->allowed();
        $requirements = Requirement::with(['items','quotationProcess.quotations.supplier'])
            ->whereIn('status',['Aprobado','Aprobado total','Aprobado parcial'])->latest('requested_at')->paginate(12);
        $suppliers = BusinessPartner::where('is_active',true)->whereIn('type',['Proveedor','Cliente y proveedor'])->orderBy('name')->get();
        return view('quotations.index',compact('requirements','suppliers'));
    }

    public function store(Request $request, Requirement $requirement)
    {
        $this->allowed();
        abort_unless(in_array($requirement->status,['Aprobado','Aprobado total','Aprobado parcial'],true),422);
        $data=$request->validate([
            'quotes'=>['required','array','min:3','max:8'], 'quotes.*.supplier_label'=>['required','string','distinct','max:300'],
            'quotes.*.file'=>['required','file','mimes:pdf,jpg,jpeg,png,webp','max:10240'], 'quotes.*.amount'=>['nullable','numeric','min:0'],
            'quotes.*.currency'=>['required',Rule::in(['PEN','USD'])], 'winner_index'=>['required','integer','min:0'],
        ]);
        if (!array_key_exists((int)$data['winner_index'],$data['quotes'])) throw ValidationException::withMessages(['winner_index'=>'Marca como ganador uno de los proveedores registrados.']);
        $available=BusinessPartner::where('is_active',true)->whereIn('type',['Proveedor','Cliente y proveedor'])->get();
        foreach($data['quotes'] as $i=>&$quote){
            $label=trim($quote['supplier_label']); $document=trim(explode('·',$label,2)[0]);
            $supplier=$available->first(fn($partner)=>$partner->document_number===$document)
                ?? $available->first(fn($partner)=>mb_strtolower($partner->name)===mb_strtolower($label));
            if(!$supplier) throw ValidationException::withMessages(["quotes.$i.supplier_label"=>'El proveedor no existe. Créalo con el botón + y vuelve a seleccionarlo.']);
            $quote['supplier_id']=$supplier->id;
        }
        unset($quote);
        if(collect($data['quotes'])->pluck('supplier_id')->duplicates()->isNotEmpty()) throw ValidationException::withMessages(['quotes'=>'Los tres proveedores deben ser diferentes.']);
        $stored=[];$old=[];
        try{
            DB::transaction(function()use($request,$requirement,$data,&$stored,&$old){
                $process=QuotationProcess::where('requirement_id',$requirement->id)->lockForUpdate()->first();
                if($process&&$process->status==='Pendiente aprobación') throw ValidationException::withMessages(['quotes'=>'La propuesta ya está esperando aprobación.']);
                if($process&&$process->status==='Aprobada') throw ValidationException::withMessages(['quotes'=>'La cotización ganadora ya fue aprobada.']);
                if(!$process) $process=QuotationProcess::create(['requirement_id'=>$requirement->id]);
                else{$old=$process->quotations()->pluck('path')->all();$process->quotations()->delete();$process->increment('version');}
                foreach($data['quotes'] as $i=>$quote){
                    $file=$request->file("quotes.$i.file");$path=$file->store('requirement-quotations','local');$stored[]=$path;
                    $process->quotations()->create(['supplier_id'=>$quote['supplier_id'],'path'=>$path,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType()?:'application/octet-stream','size'=>$file->getSize(),'amount'=>$quote['amount']??null,'currency'=>$quote['currency'],'is_winner'=>$i===(int)$data['winner_index']]);
                }
                $process->update(['status'=>'Pendiente aprobación','approval_observation'=>null,'submitted_at'=>now(),'submitted_by'=>auth()->id(),'decided_at'=>null,'decided_by'=>null]);
            });
        }catch(\Throwable $exception){Storage::disk('local')->delete($stored);throw $exception;}
        Storage::disk('local')->delete($old);
        return redirect()->route('quotations.index')->with('success','Cotizaciones enviadas correctamente a Aprobaciones → Cotizaciones ganadoras.');
    }

    public function show(RequirementQuotation $requirementQuotation)
    {
        abort_unless(auth()->user()->canAccess('logistics.quotations')||auth()->user()->canAccess('warehouse.approvals'),403);
        abort_unless(Storage::disk('local')->exists($requirementQuotation->path),404);
        return response()->file(Storage::disk('local')->path($requirementQuotation->path),['Content-Type'=>$requirementQuotation->mime_type,'X-Content-Type-Options'=>'nosniff']);
    }

    public function destroy(QuotationProcess $quotationProcess)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        $hasOrder = $quotationProcess->requirement->items()
            ->whereHas('purchaseOrderItems')->exists();
        if ($hasOrder) return back()->withErrors('Primero debes eliminar la orden vinculada a esta cotización.');
        $files = $quotationProcess->quotations()->pluck('path')->all();
        $quotationProcess->delete();
        Storage::disk('local')->delete($files);
        return back()->with('success', 'Proceso de cotización eliminado definitivamente.');
    }
}
