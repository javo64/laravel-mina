<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchWarehouseController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('products'), 403); }

    public function index()
    {
        $this->allowed();
        $branches = Branch::with(['warehouses' => fn ($query) => $query->where('is_active', true)])->where('is_active', true)->orderBy('name')->get();
        return view('branches-warehouses.index', compact('branches'));
    }

    public function storeBranch(Request $request)
    {
        $this->allowed();
        $data = $request->validate(['name'=>['required','max:150','unique:branches,name'], 'address'=>['nullable','max:255']]);
        $data['code'] = 'SUC-'.str_pad((string) ((Branch::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT);
        Branch::create([...$data, 'is_active'=>true, 'created_by'=>auth()->id()]);
        return back()->with('success', 'Sucursal registrada correctamente.');
    }

    public function storeWarehouse(Request $request)
    {
        $this->allowed();
        $data = $request->validate(['branch_id'=>['required', Rule::exists('branches','id')->where('is_active', true)], 'name'=>['required','max:150'], 'address'=>['nullable','max:255']]);
        $data['code'] = 'ALM-'.str_pad((string) ((Warehouse::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT);
        if (Warehouse::where('branch_id',$data['branch_id'])->whereRaw('LOWER(name)=?', [mb_strtolower($data['name'])])->exists()) return back()->withErrors(['name'=>'Ya existe este almacén en la sucursal seleccionada.'])->withInput();
        Warehouse::create([...$data, 'is_active'=>true, 'created_by'=>auth()->id()]);
        return back()->with('success', 'Almacén registrado correctamente.');
    }

    public function destroyBranch(Branch $branch) { $this->allowed(); $branch->update(['is_active'=>false]); $branch->warehouses()->update(['is_active'=>false]); return back()->with('success','Sucursal y almacenes retirados.'); }
    public function destroyWarehouse(Warehouse $warehouse) { $this->allowed(); $warehouse->update(['is_active'=>false]); return back()->with('success','Almacén retirado.'); }
}
