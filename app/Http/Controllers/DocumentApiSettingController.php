<?php

namespace App\Http\Controllers;

use App\Models\DocumentApiSetting;
use Illuminate\Http\Request;

class DocumentApiSettingController extends Controller
{
    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('administration.document-api'), 403);
    }

    public function edit()
    {
        $this->allowed();
        $setting = DocumentApiSetting::current()->load('updater');

        return view('settings.document-api', compact('setting'));
    }

    public function update(Request $request)
    {
        $this->allowed();
        $setting = DocumentApiSetting::current();
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:1000'],
            'token' => ['nullable', 'string', 'min:10', 'max:2000'],
        ]);
        if ($setting->hasUnreadableToken() && blank($data['token'] ?? null)) {
            return back()->withErrors(['token' => 'El token anterior no se puede leer. Ingresa un nuevo token para reemplazarlo.'])->withInput();
        }
        $values = [
            'url' => trim($data['url']),
            'is_active' => $request->boolean('is_active'),
            'updated_by' => auth()->id(),
        ];
        if (filled($data['token'] ?? null)) {
            $values['token'] = trim($data['token']);
        }
        $setting->update($values);

        return redirect()->route('settings.document-api.edit')
            ->with('success', 'Configuración de la API de documentos guardada.');
    }
}
