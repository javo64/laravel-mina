<?php

namespace App\Http\Controllers;

use App\Models\Responsible;
use App\Models\Requirement;
use Illuminate\Http\Request;

class ResponsibleController extends Controller
{
    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('requirements'), 403);
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'name' => ['required', 'max:255', 'unique:responsibles,name'],
            'position' => ['nullable', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $data['is_active'] = true;
        Responsible::create($data);

        return back()->with('success', 'Responsable registrado correctamente.');
    }

    public function destroy(Responsible $responsible)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if (Requirement::where('responsible', $responsible->name)->exists()) return back()->withErrors('No se puede eliminar el responsable porque tiene requerimientos vinculados.');
        $responsible->delete();

        return back()->with('success', 'Responsable eliminado correctamente.');
    }
}
