<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Requirement;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('requirements'), 403); }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate(['name' => ['required','max:150','unique:areas,name'], 'description' => ['nullable','max:500']]);
        $data['is_active'] = true;
        Area::create($data);
        return back()->with('success', 'Área registrada correctamente.');
    }

    public function destroy(Area $area)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if (Requirement::where('area', $area->name)->exists()) return back()->withErrors('No se puede eliminar el área porque tiene requerimientos vinculados.');
        $area->delete();
        return back()->with('success', 'Área eliminada correctamente.');
    }
}
