<?php

namespace App\Http\Controllers;

use App\Models\BusinessPartner;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductReception;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('warehouse.inventory'), 403); }

    /**
     * Central de operaciones de almacén. Mantiene separadas las existencias
     * físicas por almacén de la ficha comercial del producto.
     */
    public function dashboard()
    {
        $this->allowed();

        $warehouses = Warehouse::where('is_active', true)->count();
        $products = Product::where('is_active', true)->where('type', 'Producto')->get(['id', 'code', 'name', 'unit', 'min_stock']);
        $stocks = InventoryStock::with('warehouse.branch')
            ->whereIn('product_id', $products->pluck('id'))
            ->get();
        $stockByProduct = $stocks->groupBy('product_id')->map(fn ($rows) => (float) $rows->sum('quantity'));
        $criticalProducts = $products
            ->filter(fn ($product) => (float) $product->min_stock > 0 && ($stockByProduct[$product->id] ?? 0) <= (float) $product->min_stock)
            ->map(function ($product) use ($stockByProduct) {
                $product->available_stock = $stockByProduct[$product->id] ?? 0;
                return $product;
            })->sortBy('available_stock')->values();

        $today = now()->toDateString();
        $todayEntries = InventoryMovement::where('type', 'Entrada')->whereDate('occurred_at', $today)->count();
        $todayTransfers = InventoryMovement::where('type', 'Traslado')->whereDate('occurred_at', $today)->count();
        $pendingReceipts = ProductReception::query()->whereHas('purchaseOrder', fn ($query) => $query->whereIn('receipt_status', ['Pendiente', 'Parcial']))->count();
        $recentMovements = InventoryMovement::with(['product', 'sourceWarehouse', 'destinationWarehouse'])
            ->latest('occurred_at')->latest('id')->limit(8)->get();

        return view('inventory.dashboard', compact(
            'warehouses', 'products', 'stocks', 'criticalProducts', 'todayEntries', 'todayTransfers', 'pendingReceipts', 'recentMovements'
        ));
    }

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

    public function destroy(InventoryMovement $inventoryMovement)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if ($inventoryMovement->product_reception_item_id) {
            return back()->withErrors('Las entradas deben revertirse desde Recepción de Productos.');
        }
        if (InventoryMovement::where('product_id',$inventoryMovement->product_id)->where('id','>',$inventoryMovement->id)->exists()) {
            return back()->withErrors('No se puede eliminar porque el producto tiene movimientos posteriores. Elimina primero el movimiento más reciente.');
        }
        DB::transaction(function () use ($inventoryMovement): void {
            $quantity=(float)$inventoryMovement->quantity;
            $product=Product::whereKey($inventoryMovement->product_id)->lockForUpdate()->firstOrFail();
            if ($inventoryMovement->type==='Traslado') {
                $source=InventoryStock::where('product_id',$product->id)->where('warehouse_id',$inventoryMovement->source_warehouse_id)->lockForUpdate()->firstOrFail();
                $destination=InventoryStock::where('product_id',$product->id)->where('warehouse_id',$inventoryMovement->destination_warehouse_id)->lockForUpdate()->firstOrFail();
                if((float)$destination->quantity<$quantity) throw ValidationException::withMessages(['movement'=>'El almacén destino ya no tiene stock suficiente para revertir el traslado.']);
                $destination->decrement('quantity',$quantity); $source->increment('quantity',$quantity);
            } elseif ($inventoryMovement->type==='Devolución') {
                $stock=InventoryStock::where('product_id',$product->id)->where('warehouse_id',$inventoryMovement->source_warehouse_id)->lockForUpdate()->firstOrFail();
                $stock->increment('quantity',$quantity); $product->increment('stock',$quantity);
            } else {
                throw ValidationException::withMessages(['movement'=>'Este tipo de movimiento no puede eliminarse directamente.']);
            }
            $inventoryMovement->delete();
        });
        return back()->with('success','Movimiento eliminado y existencias revertidas correctamente.');
    }

    private function nextCode(string $prefix): string
    {
        $next=(InventoryMovement::lockForUpdate()->where('code','like',$prefix.'-'.now()->year.'-%')->max('id')??0)+1;
        return $prefix.'-'.now()->year.'-'.str_pad((string)$next,5,'0',STR_PAD_LEFT);
    }
}
