<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('requirements'), 403); }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate(['name' => ['required','max:255','unique:projects,name'], 'description' => ['nullable','max:500']]);
        $data['code'] = 'PRY-'.str_pad((string) ((Project::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT);
        $data['is_active'] = true;
        Project::create($data);
        return back()->with('success', 'Proyecto registrado correctamente.');
    }

    public function destroy(Project $project)
    {
        $this->allowed();
        $project->update(['is_active' => false]);
        return back()->with('success', 'Proyecto retirado de la lista.');
    }
}
