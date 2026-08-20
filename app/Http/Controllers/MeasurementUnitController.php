<?php

namespace App\Http\Controllers;

use App\Models\MeasurementUnit;
use App\Models\Product;
use Illuminate\Http\Request;

class MeasurementUnitController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('products'), 403); }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate(['name' => ['required','max:100','unique:measurement_units,name'], 'symbol' => ['required','max:15','unique:measurement_units,symbol']]);
        $data['is_active'] = true;
        MeasurementUnit::create($data);
        return back()->with('success', 'Unidad de medida registrada correctamente.');
    }

    public function destroy(MeasurementUnit $unit)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if (Product::where('unit', $unit->symbol)->orWhere('unit', $unit->name)->exists()) return back()->withErrors('No se puede eliminar la unidad porque tiene productos vinculados.');
        $unit->delete();
        return back()->with('success', 'Unidad de medida eliminada correctamente.');
    }
}
