<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Models\ProductCategory;
use App\Models\ProductReceptionItem;
use App\Models\RequirementItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use App\Models\ProductGroup;
use App\Models\ProductSubgroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('warehouse.products'), 403); }

    public function index(Request $request)
    {
        $this->allowed();
        $products = Product::with(['productGroup', 'productSubgroup'])->where('is_active', true)
            ->when($request->q, fn ($q, $value) => $q->where(fn ($search) => $search->where('name','like',"%$value%")->orWhere('code','like',"%$value%")->orWhere('category','like',"%$value%")))
            ->latest()->paginate(10);
        $categories = ProductCategory::where('is_active', true)->orderBy('name')->get();
        $units = MeasurementUnit::where('is_active', true)->orderBy('name')->get();
        $groups = ProductGroup::with(['subgroups' => fn ($query) => $query->where('is_active', true)])->where('is_active', true)->orderBy('code')->get();
        $groupData = $groups->map(function (ProductGroup $group): array {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'subgroups' => $group->subgroups->map(fn (ProductSubgroup $subgroup): array => [
                    'id' => $subgroup->id,
                    'code' => $subgroup->code,
                    'name' => $subgroup->name,
                ])->values()->all(),
            ];
        })->values()->all();
        return view('products.index', compact('products', 'categories', 'units', 'groups', 'groupData'));
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $this->validated($request);
        $this->uppercaseText($data);
        $prefix = $data['type'] === 'Servicio' ? 'SRV-' : 'PRD-';
        $data['code'] = ($data['code'] ?? null) ?: $prefix.str_pad((string)((Product::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
        $this->setFlags($request, $data);
        DB::transaction(function () use ($data): void {
            $product = Product::create($data);
            $this->syncInventory($product);
        });
        return back()->with('success', 'Producto o servicio guardado correctamente.');
    }

    public function update(Request $request, Product $product)
    {
        $this->allowed();
        $data = $this->validated($request, $product);
        $this->uppercaseText($data);
        $this->setFlags($request, $data);
        DB::transaction(function () use ($product, $data): void {
            $product->update($data);
            $this->syncInventory($product);
        });
        return back()->with('success', 'Producto o servicio actualizado.');
    }

    public function destroy(Product $product)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if (RequirementItem::where('product_id', $product->id)->exists()
            || ProductReceptionItem::where('product_id', $product->id)->exists()) {
            return back()->withErrors('No se puede eliminar el producto o servicio porque está vinculado a un requerimiento o una recepción.');
        }
        $product->delete();
        return back()->with('success', 'Producto o servicio eliminado correctamente.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['Producto','Servicio'])],
            'operation_type' => ['nullable', Rule::in(['Compra', 'Venta'])],
            'name' => ['required','max:255'], 'secondary_name' => ['required','max:255'],
            'description' => ['required','max:1000'],
            'code' => ['nullable','max:50', Rule::unique('products','code')->ignore($product?->id)],
            'barcode' => ['required','max:100'], 'category' => ['required','max:100'],
            'unit' => ['required','max:50'], 'currency' => ['nullable', Rule::in(['PEN','USD'])],
            'price' => ['nullable','numeric','min:0'], 'stock' => ['nullable','integer','min:0'],
            'min_stock' => ['nullable','integer','min:0'], 'warehouse' => ['nullable','max:150'],
            'tax_affectation' => ['required','max:150'],
            'product_group_id' => ['nullable', Rule::exists('product_groups', 'id')],
            'product_subgroup_id' => ['nullable', Rule::exists('product_subgroups', 'id')],
        ]);
        if (!empty($data['product_subgroup_id'])) {
            $subgroup = ProductSubgroup::find($data['product_subgroup_id']);
            if (! $subgroup || ($data['product_group_id'] ?? null) != $subgroup->product_group_id) {
                throw ValidationException::withMessages(['product_subgroup_id' => 'El subgrupo no pertenece al grupo seleccionado.']);
            }
            $data['category'] = $subgroup->name;
        }
        $data['operation_type'] = $data['operation_type'] ?? 'Venta';
        if ($data['operation_type'] === 'Compra') {
            if ($product && (float) InventoryStock::where('product_id', $product->id)->sum('quantity') > 0) {
                throw ValidationException::withMessages(['operation_type' => 'No se puede cambiar a Compra un ítem que tiene existencias. Regulariza primero el stock desde Inventario.']);
            }
            $data['price'] = 0;
            $data['stock'] = 0;
            $data['min_stock'] = 0;
        }
        $data['currency'] = $data['currency'] ?? 'PEN';
        return $data;
    }

    private function setFlags(Request $request, array &$data): void
    {
        $data['includes_tax'] = $request->boolean('includes_tax');
        $data['price'] = $data['price'] ?? 0;
        $data['stock'] = $data['stock'] ?? 0;
        $data['min_stock'] = $data['min_stock'] ?? 0;
    }

    private function uppercaseText(array &$data): void
    {
        foreach (['name', 'secondary_name', 'description', 'barcode', 'category', 'warehouse', 'tax_affectation'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) $data[$field] = mb_strtoupper($data[$field]);
        }
    }

    private function syncInventory(Product $product): void
    {
        if ($product->type !== 'Producto' || $product->operation_type === 'Compra') return;
        $warehouse = Warehouse::whereRaw('UPPER(name) = ?', [mb_strtoupper($product->warehouse)])->first()
            ?? Warehouse::where('is_active', true)->orderBy('id')->firstOrFail();
        $stock = InventoryStock::firstOrCreate(['product_id'=>$product->id,'warehouse_id'=>$warehouse->id],['quantity'=>0]);
        $stock = InventoryStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
        $registered = (float) InventoryStock::where('product_id', $product->id)->lockForUpdate()->sum('quantity');
        $difference = (float) $product->stock - $registered;
        if ($difference === 0.0) return;
        if ($difference < 0 && (float)$stock->quantity < abs($difference)) {
            throw ValidationException::withMessages(['stock'=>'El stock no puede reducirse porque las existencias están distribuidas en otros almacenes. Registra las salidas desde Inventario.']);
        }
        $difference > 0 ? $stock->increment('quantity',$difference) : $stock->decrement('quantity',abs($difference));
        InventoryMovement::create(['code'=>'AJU-'.now()->format('YmdHis').'-'.$product->id,'occurred_at'=>now(),'type'=>'Ajuste','product_id'=>$product->id,'destination_warehouse_id'=>$difference>0?$warehouse->id:null,'source_warehouse_id'=>$difference<0?$warehouse->id:null,'quantity'=>abs($difference),'reference'=>'Ficha de producto','created_by'=>auth()->id()]);
    }
}
