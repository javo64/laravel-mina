<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
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
        $this->allowed();
        $category->update(['is_active' => false]);
        return back()->with('success', 'Categoría retirada de la lista.');
    }
}
