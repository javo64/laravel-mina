<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductSubgroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductGroupController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('warehouse.products'), 403); }

    public function storeGroup(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'code' => ['required', 'max:20', Rule::unique('product_groups', 'code')->where(fn ($query) => $query->where('company_id', \App\Support\ActiveCompany::id()))],
            'name' => ['required', 'max:150'],
            'item_type' => ['required', Rule::in(['Producto', 'Servicio', 'Ambos'])],
        ]);
        ProductGroup::create([...$data, 'code' => mb_strtoupper($data['code']), 'name' => mb_strtoupper($data['name']), 'is_active' => true]);
        return back()->with('success', 'Grupo registrado correctamente.');
    }

    public function storeSubgroup(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'product_group_id' => ['required', Rule::exists('product_groups', 'id')],
            'code' => ['required', 'max:20'], 'name' => ['required', 'max:150'],
            'account' => ['nullable', 'max:50'], 'inventory_account' => ['nullable', 'max:50'], 'expense_type' => ['nullable', 'max:100'],
        ]);
        if (ProductSubgroup::where('product_group_id', $data['product_group_id'])->where('code', $data['code'])->exists()) {
            return back()->withErrors(['subgroup_code' => 'El código de subgrupo ya existe dentro del grupo seleccionado.'])->withInput();
        }
        ProductSubgroup::create([...$data, 'code' => mb_strtoupper($data['code']), 'name' => mb_strtoupper($data['name']), 'is_active' => true]);
        return back()->with('success', 'Subgrupo registrado correctamente.');
    }

    public function destroyGroup(ProductGroup $productGroup)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if ($productGroup->subgroups()->exists() || Product::where('product_group_id', $productGroup->id)->exists()) return back()->withErrors('No se puede eliminar el grupo porque tiene subgrupos o productos vinculados.');
        $productGroup->delete();
        return back()->with('success', 'Grupo eliminado correctamente.');
    }

    public function destroySubgroup(ProductSubgroup $productSubgroup)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if (Product::where('product_subgroup_id', $productSubgroup->id)->exists()) return back()->withErrors('No se puede eliminar el subgrupo porque tiene productos vinculados.');
        $productSubgroup->delete();
        return back()->with('success', 'Subgrupo eliminado correctamente.');
    }
}
