<?php

namespace App\Http\Controllers;

use App\Models\Area;
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
        $this->allowed();
        $area->update(['is_active' => false]);
        return back()->with('success', 'Área retirada de la lista.');
    }
}
