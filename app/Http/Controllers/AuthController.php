<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }
    public function login(Request $request) {
        $credentials = $request->validate(['email' => ['required','email'], 'password' => ['required']]);
        if (!Auth::attempt($credentials, $request->boolean('remember'))) return back()->withErrors(['email' => 'El correo o la contraseña no son correctos.'])->onlyInput('email');
        if (!Auth::user()->is_active) { Auth::logout(); return back()->withErrors(['email' => 'Este usuario está inactivo.']); }
        $request->session()->regenerate(); Auth::user()->update(['last_access_at' => now()]);
        $first = Auth::user()->permissions[0] ?? 'products'; return redirect()->route($first === 'users' ? 'users.index' : $first.'.index');
    }
    public function logout(Request $request) { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
}
