<?php

namespace App\Http\Controllers;

use App\Models\BusinessPartner;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('warehouse.inventory'), 403); }

    public function index(Request $request)
    {
        $this->allowed();
        $tab = in_array($request->string('tab')->toString(), ['movimientos','kardex','inventario','devoluciones'], true) ? $request->string('tab')->toString() : 'movimientos';
        $products = Product::where('is_active',true)->where('type','Producto')->orderBy('name')->get();
        $warehouses = Warehouse::with('branch')->where('is_active',true)->orderBy('name')->get();
        $suppliers = BusinessPartner::where('is_active',true)->whereIn('type',['Proveedor','Cliente y proveedor'])->orderBy('name')->get();
        $movementQuery = InventoryMovement::with(['product','sourceWarehouse.branch','destinationWarehouse.branch','supplier','creator'])
            ->when($request->product_id, fn($q,$id)=>$q->where('product_id',$id))
            ->when($request->warehouse_id, fn($q,$id)=>$q->where(fn($w)=>$w->where('source_warehouse_id',$id)->orWhere('destination_warehouse_id',$id)))
            ->when($request->date_from, fn($q,$date)=>$q->whereDate('occurred_at','>=',$date))
            ->when($request->date_to, fn($q,$date)=>$q->whereDate('occurred_at','<=',$date));
        $movements = (clone $movementQuery)->latest('occurred_at')->latest('id')->paginate(20)->withQueryString();
        $returns = InventoryMovement::with(['product','sourceWarehouse','supplier','creator'])->where('type','Devolución')->latest('occurred_at')->paginate(15,['*'],'returns_page')->withQueryString();
        $warehouseProducts = InventoryStock::with(['product','warehouse.branch'])
            ->whereHas('product',fn($q)=>$q->where('is_active',true)->where('type','Producto'))
            ->orderBy('warehouse_id')->orderBy('product_id')->get();
        $stocks = InventoryStock::with(['product','warehouse.branch'])->where('quantity','>',0)
            ->when($request->warehouse_id,fn($q,$id)=>$q->where('warehouse_id',$id))
            ->when($request->product_id,fn($q,$id)=>$q->where('product_id',$id))
            ->orderBy('warehouse_id')->orderBy('product_id')->get();

        return view('inventory.index', compact('tab','products','warehouses','suppliers','movements','returns','stocks','warehouseProducts'));
    }

    public function transfer(Request $request)
    {
        $this->allowed();
        $data=$request->validate(['occurred_at'=>['required','date'],'product_id'=>['required','exists:products,id'],'source_warehouse_id'=>['required','exists:warehouses,id'],'destination_warehouse_id'=>['required','different:source_warehouse_id','exists:warehouses,id'],'quantity'=>['required','integer','min:1'],'notes'=>['nullable','max:1000']]);
        DB::transaction(function() use($data): void {
            $source=InventoryStock::where('product_id',$data['product_id'])->where('warehouse_id',$data['source_warehouse_id'])->lockForUpdate()->first();
            if(!$source || (float)$source->quantity < (float)$data['quantity']) throw ValidationException::withMessages(['quantity'=>'La cantidad supera el stock disponible en el almacén de origen.']);
            $destination=InventoryStock::firstOrCreate(['product_id'=>$data['product_id'],'warehouse_id'=>$data['destination_warehouse_id']],['quantity'=>0]);
            $destination=InventoryStock::whereKey($destination->id)->lockForUpdate()->firstOrFail();
            $source->decrement('quantity',$data['quantity']); $destination->increment('quantity',$data['quantity']);
            InventoryMovement::create([...$data,'code'=>$this->nextCode('TRS'),'type'=>'Traslado','created_by'=>auth()->id()]);
        });
        return redirect()->route('inventory.index',['tab'=>'movimientos'])->with('success','Traslado registrado correctamente.');
    }

    public function supplierReturn(Request $request)
    {
        $this->allowed();
        $data=$request->validate(['occurred_at'=>['required','date'],'supplier_id'=>['required',Rule::exists('business_partners','id')->where(fn($q)=>$q->where('is_active',true)->whereIn('type',['Proveedor','Cliente y proveedor']))],'product_id'=>['required','exists:products,id'],'source_warehouse_id'=>['required','exists:warehouses,id'],'quantity'=>['required','integer','min:1'],'reference'=>['required','max:150'],'notes'=>['nullable','max:1000']]);
        DB::transaction(function() use($data): void {
            $stock=InventoryStock::where('product_id',$data['product_id'])->where('warehouse_id',$data['source_warehouse_id'])->lockForUpdate()->first();
            if(!$stock || (float)$stock->quantity < (float)$data['quantity']) throw ValidationException::withMessages(['quantity'=>'La cantidad a devolver supera el stock disponible.']);
            $product=Product::lockForUpdate()->findOrFail($data['product_id']);
            $stock->decrement('quantity',$data['quantity']); $product->decrement('stock',$data['quantity']);
            InventoryMovement::create([...$data,'code'=>$this->nextCode('DEV'),'type'=>'Devolución','created_by'=>auth()->id()]);
        });
        return redirect()->route('inventory.index',['tab'=>'devoluciones'])->with('success','Documento de devolución registrado y stock actualizado.');
    }

    private function nextCode(string $prefix): string
    {
        $next=(InventoryMovement::lockForUpdate()->where('code','like',$prefix.'-'.now()->year.'-%')->max('id')??0)+1;
        return $prefix.'-'.now()->year.'-'.str_pad((string)$next,5,'0',STR_PAD_LEFT);
    }
}
