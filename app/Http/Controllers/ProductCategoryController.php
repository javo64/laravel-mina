<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('products'), 403); }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate(['name' => ['required','max:100','unique:product_categories,name'], 'description' => ['nullable','max:500']]);
        $data['is_active'] = true;
        ProductCategory::create($data);
        return back()->with('success', 'Categoría registrada correctamente.');
    }

    public function destroy(ProductCategory $category)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if (Product::where('category', $category->name)->exists()) return back()->withErrors('No se puede eliminar la categoría porque tiene productos vinculados.');
        $category->delete();
        return back()->with('success', 'Categoría eliminada correctamente.');
    }
}
