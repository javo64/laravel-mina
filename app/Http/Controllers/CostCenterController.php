<?php

namespace App\Http\Controllers;

use App\Models\CostCenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostCenterController extends Controller
{
    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('costs'), 403);
    }

    public function index()
    {
        $this->allowed();
        $parents = CostCenter::with(['children' => fn ($query) => $query->where('is_active', true)])
            ->whereNull('parent_id')->where('is_active', true)->orderBy('name')->get();
        $inactive = CostCenter::where('is_active', false)->count();

        return view('cost-centers.index', compact('parents', 'inactive'));
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'parent_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if (CostCenter::where('parent_id', $data['parent_id'] ?? null)->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->exists()) {
            return back()->withErrors(['name' => 'Ya existe un centro de costo con este nombre en el mismo grupo.'])->withInput();
        }

        $data['code'] = 'CC-'.str_pad((string) ((CostCenter::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT);
        CostCenter::create([...$data, 'is_active' => true, 'created_by' => auth()->id()]);

        return back()->with('success', empty($data['parent_id']) ? 'Grupo de centro de costos creado.' : 'Centro de costo agregado correctamente.');
    }

    public function update(Request $request, CostCenter $costCenter)
    {
        $this->allowed();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $costCenter->update($data);

        return back()->with('success', 'Centro de costos actualizado.');
    }

    public function destroy(CostCenter $costCenter)
    {
        $this->allowed();
        $costCenter->update(['is_active' => false]);
        if (! $costCenter->parent_id) {
            $costCenter->children()->update(['is_active' => false]);
        }

        return back()->with('success', 'Centro de costos retirado de la lista.');
    }
}
