<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('users'), 403); }
    public function index(Request $request) { $this->allowed(); $users = User::when($request->q, fn($q,$v)=>$q->where(fn($x)=>$x->where('name','like',"%$v%")->orWhere('email','like',"%$v%")))->latest()->paginate(10); return view('users.index', compact('users')); }
    public function store(Request $request) { $this->allowed(); $data=$request->validate(['name'=>'required|max:255','email'=>'required|email|unique:users','password'=>'required|min:8','branch'=>'required|max:255','profile'=>'required|max:100','permissions'=>'required|array|min:1','permissions.*'=>'in:products,requirements,approvals,logistics,users']); $data['is_active']=$request->boolean('is_active'); User::create($data); return back()->with('success','Usuario creado correctamente.'); }
    public function update(Request $request, User $user) { $this->allowed(); $data=$request->validate(['name'=>'required|max:255','email'=>['required','email',Rule::unique('users')->ignore($user->id)],'password'=>'nullable|min:8','branch'=>'required|max:255','profile'=>'required|max:100','permissions'=>'required|array|min:1','permissions.*'=>'in:products,requirements,approvals,logistics,users']); if(empty($data['password'])) unset($data['password']); $data['is_active']=$request->boolean('is_active'); $user->update($data); return back()->with('success','Usuario actualizado.'); }
}
