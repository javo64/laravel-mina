<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Company;
use App\Support\ActiveCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchWarehouseController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('warehouse.structure'), 403); }

    public function index()
    {
        $this->allowed();
        $companies = Company::with(['branches'=>fn($query)=>$query->where('is_active',true)->with(['warehouses'=>fn($warehouses)=>$warehouses->where('is_active',true)])])->where('is_active',true)->when(ActiveCompany::id(), fn ($query, $id) => $query->whereKey($id))->orderBy('id')->get();
        return view('branches-warehouses.index', compact('companies'));
    }

    public function storeCompany(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'ruc' => ['required', 'digits:11', 'unique:companies,ruc'],
            'legal_name' => ['required', 'string', 'max:150', 'unique:companies,legal_name'],
        ]);

        $data['code'] = 'EMP-'.str_pad((string) ((Company::max('id') ?? 0) + 1), 3, '0', STR_PAD_LEFT);
        $data['name'] = $data['legal_name'];
        $company = Company::create([...$data, 'is_active' => true]);
        $request->session()->put('active_company_id', $company->id);

        return back()->with('success', 'Empresa registrada correctamente. Ahora puedes agregar sus sucursales y almacenes.');
    }

    public function storeBranch(Request $request)
    {
        $this->allowed();
        $data = $request->validate(['company_id'=>['required',Rule::exists('companies','id')->where('is_active',true)],'name'=>['required','max:150','unique:branches,name'], 'address'=>['nullable','max:255']]);
        abort_unless((int) $data['company_id'] === (int) ActiveCompany::id(), 403);
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
        Warehouse::create([...$data, 'company_id'=>ActiveCompany::id(), 'is_active'=>true, 'created_by'=>auth()->id()]);
        return back()->with('success', 'Almacén registrado correctamente.');
    }

    public function activateCompany(Request $request)
    {
        abort_unless(auth()->check(), 403);
        $data = $request->validate(['company_id' => ['required', Rule::exists('companies', 'id')->where('is_active', true)]]);
        $request->session()->put('active_company_id', $data['company_id']);
        return redirect()->route('warehouse.dashboard')->with('success', 'Empresa activa actualizada correctamente.');
    }

    public function destroyBranch(Branch $branch)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        $warehouseNames = $branch->warehouses()->pluck('name');
        if ($branch->warehouses()->exists()) return back()->withErrors('No se puede eliminar la sucursal mientras tenga almacenes. Elimine primero los almacenes sin transacciones.');
        if (PurchaseOrder::where('destination_branch', $branch->name)->exists() || Product::whereIn('warehouse', $warehouseNames)->exists()) return back()->withErrors('No se puede eliminar la sucursal porque tiene transacciones vinculadas.');
        $branch->delete();
        return back()->with('success','Sucursal eliminada correctamente.');
    }

    public function destroyWarehouse(Warehouse $warehouse)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if (PurchaseOrder::where('destination_warehouse', $warehouse->name)->exists() || Product::where('warehouse', $warehouse->name)->exists()) return back()->withErrors('No se puede eliminar el almacén porque tiene productos u órdenes vinculadas.');
        $warehouse->delete();
        return back()->with('success','Almacén eliminado correctamente.');
    }
}
