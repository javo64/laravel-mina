<?php

namespace App\Http\Controllers;

use App\Models\MeasurementUnit;
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
        $this->allowed();
        $unit->update(['is_active' => false]);
        return back()->with('success', 'Unidad de medida retirada de la lista.');
    }
}
