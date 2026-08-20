<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Models\ProductCategory;
use App\Models\ProductReceptionItem;
use App\Models\RequirementItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('products'), 403); }

    public function index(Request $request)
    {
        $this->allowed();
        $products = Product::where('is_active', true)
            ->when($request->q, fn ($q, $value) => $q->where(fn ($search) => $search->where('name','like',"%$value%")->orWhere('code','like',"%$value%")->orWhere('category','like',"%$value%")))
            ->latest()->paginate(10);
        $categories = ProductCategory::where('is_active', true)->orderBy('name')->get();
        $units = MeasurementUnit::where('is_active', true)->orderBy('name')->get();
        return view('products.index', compact('products', 'categories', 'units'));
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $this->validated($request);
        $prefix = $data['type'] === 'Servicio' ? 'SRV-' : 'PRD-';
        $data['code'] = ($data['code'] ?? null) ?: $prefix.str_pad((string)((Product::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
        $this->setFlags($request, $data);
        Product::create($data);
        return back()->with('success', 'Producto o servicio guardado correctamente.');
    }

    public function update(Request $request, Product $product)
    {
        $this->allowed();
        $data = $this->validated($request, $product);
        $this->setFlags($request, $data);
        $product->update($data);
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
        return $request->validate([
            'type' => ['required', Rule::in(['Producto','Servicio'])],
            'name' => ['required','max:255'], 'secondary_name' => ['nullable','max:255'],
            'description' => ['nullable','max:1000'],
            'code' => ['nullable','max:50', Rule::unique('products','code')->ignore($product?->id)],
            'barcode' => ['nullable','max:100'], 'category' => ['nullable','max:100'],
            'unit' => ['required','max:50'], 'currency' => ['required', Rule::in(['PEN','USD'])],
            'price' => ['required','numeric','min:0'], 'stock' => ['nullable','integer','min:0'],
            'min_stock' => ['nullable','integer','min:0'], 'warehouse' => ['required','max:150'],
            'tax_affectation' => ['required','max:150'],
        ]);
    }

    private function setFlags(Request $request, array &$data): void
    {
        $data['includes_tax'] = $request->boolean('includes_tax');
        $data['stock'] = $data['stock'] ?? 0;
        $data['min_stock'] = $data['min_stock'] ?? 0;
    }
}
