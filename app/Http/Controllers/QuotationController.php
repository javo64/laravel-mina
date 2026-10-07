<?php

namespace App\Http\Controllers;

use App\Models\BusinessPartner;
use App\Models\QuotationProcess;
use App\Models\Requirement;
use App\Models\RequirementItem;
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
        $availableItems = RequirementItem::with('requirement')
            ->whereIn('approval_status', ['Aprobado', 'Aprobado parcial'])
            ->whereHas('requirement', fn ($query) => $query->whereIn('status', ['Aprobado', 'Aprobado total', 'Aprobado parcial', 'Aprobación']))
            ->whereDoesntHave('purchaseOrderItems')
            ->whereDoesntHave('quotationProcesses', fn ($query) => $query->whereIn('status', ['Pendiente aprobación', 'Aprobada']))
            ->latest('id')->get();
        $requirements = $availableItems->groupBy('requirement_id');
        $processes = QuotationProcess::with(['items.requirement', 'quotations.supplier'])
            ->latest('id')->paginate(12);
        $suppliers = BusinessPartner::where('is_active',true)->whereIn('type',['Proveedor','Cliente y proveedor'])->orderBy('name')->get();
        return view('quotations.index',compact('requirements','availableItems','processes','suppliers'));
    }

    public function store(Request $request, Requirement $requirement)
    {
        $request->merge(['item_ids' => $requirement->items()
            ->whereIn('approval_status', ['Aprobado', 'Aprobado parcial'])->pluck('id')->all()]);

        return $this->storeBatch($request);
    }

    public function storeBatch(Request $request)
    {
        $this->allowed();
        $data=$request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'distinct', 'exists:requirement_items,id'],
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
            DB::transaction(function()use($request,$data,&$stored){
                $items = RequirementItem::with('requirement')
                    ->whereKey($data['item_ids'])
                    ->whereIn('approval_status', ['Aprobado', 'Aprobado parcial'])
                    ->whereHas('requirement', fn ($query) => $query->whereIn('status', ['Aprobado', 'Aprobado total', 'Aprobado parcial', 'Aprobación']))
                    ->whereDoesntHave('purchaseOrderItems')
                    ->whereDoesntHave('quotationProcesses', fn ($query) => $query->whereIn('status', ['Pendiente aprobación', 'Aprobada']))
                    ->lockForUpdate()->get();
                if ($items->count() !== count($data['item_ids'])) {
                    throw ValidationException::withMessages(['item_ids' => 'Uno o más ítems ya no están disponibles para cotizar. Actualiza la página e inténtalo nuevamente.']);
                }
                $process = QuotationProcess::create(['requirement_id' => $items->first()->requirement_id]);
                $process->update(['block_code' => 'COT-'.now()->year.'-'.str_pad((string) $process->id, 4, '0', STR_PAD_LEFT)]);
                $process->items()->sync($items->pluck('id'));
                foreach($data['quotes'] as $i=>$quote){
                    $file=$request->file("quotes.$i.file");$path=$file->store('requirement-quotations','local');$stored[]=$path;
                    $process->quotations()->create(['supplier_id'=>$quote['supplier_id'],'path'=>$path,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType()?:'application/octet-stream','size'=>$file->getSize(),'amount'=>$quote['amount']??null,'currency'=>$quote['currency'],'is_winner'=>$i===(int)$data['winner_index']]);
                }
                $process->update(['status'=>'Pendiente aprobación','approval_observation'=>null,'submitted_at'=>now(),'submitted_by'=>auth()->id(),'decided_at'=>null,'decided_by'=>null]);
            });
        }catch(\Throwable $exception){Storage::disk('local')->delete($stored);throw $exception;}
        return redirect()->route('quotations.index')->with('success','Bloque de cotización enviado correctamente a Aprobaciones → Cotizaciones ganadoras.');
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
        $hasOrder = $quotationProcess->items()->whereHas('purchaseOrderItems')->exists();
        if ($hasOrder) return back()->withErrors('Primero debes eliminar la orden vinculada a esta cotización.');
        $files = $quotationProcess->quotations()->pluck('path')->all();
        $quotationProcess->delete();
        Storage::disk('local')->delete($files);
        return back()->with('success', 'Proceso de cotización eliminado definitivamente.');
    }
}
