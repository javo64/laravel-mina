<?php

namespace App\Http\Controllers;

use App\Models\OpenAiSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpenAiSettingController extends Controller
{
    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('users'), 403);
    }

    public function edit()
    {
        $this->allowed();
        $setting = OpenAiSetting::current()->load('updater');

        return view('settings.openai', compact('setting'));
    }

    public function update(Request $request)
    {
        $this->allowed();
        $setting = OpenAiSetting::current();
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'min:20', 'max:500'],
            'model' => ['required', Rule::in(['gpt-5.6-sol'])],
        ]);

        $values = [
            'model' => $data['model'],
            'is_active' => $request->boolean('is_active'),
            'updated_by' => auth()->id(),
        ];
        if (filled($data['api_key'] ?? null)) {
            $values['api_key'] = trim($data['api_key']);
        }

        $setting->update($values);

        return redirect()->route('settings.openai.edit')
            ->with('success', 'Credenciales de OpenAI guardadas de forma segura.');
    }
}
