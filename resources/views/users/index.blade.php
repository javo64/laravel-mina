@extends('layouts.app')
@section('title','Usuarios')
@section('content')
<div class="breadcrumb">ADMINISTRACIÓN › Usuarios</div>
<div class="heading"><div><h1>Usuarios</h1><p>Crea cuentas y define módulos autorizados.</p></div><button class="primary" onclick="document.getElementById('new-user').showModal()">＋ Nuevo usuario</button></div>
<div class="stats">
    <article><span>♙</span><div><small>Total</small><strong>{{ \App\Models\User::count() }}</strong></div></article>
    <article><span>✓</span><div><small>Activos</small><strong>{{ \App\Models\User::where('is_active',true)->count() }}</strong></div></article>
    <article><span>◆</span><div><small>Administradores</small><strong>{{ \App\Models\User::where('profile','Administrador')->count() }}</strong></div></article>
</div>
<div class="card">
    <form class="toolbar"><label>⌕ <input name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o correo..."></label><button>Buscar</button></form>
    <div class="table-wrap"><table><thead><tr><th>Usuario</th><th>Correo</th><th>Sucursal</th><th>Perfil</th><th>Permisos</th><th>Último acceso</th><th>Estado</th><th></th></tr></thead><tbody>
    @foreach($users as $user)
        <tr><td><strong>{{ $user->name }}</strong></td><td>{{ $user->email }}</td><td>{{ $user->branch }}</td><td><em>{{ $user->profile }}</em></td><td><div class="tags">@foreach($user->permissions??[] as $permission)<span>{{ ['products'=>'Productos','requirements'=>'Requerimientos','approvals'=>'Aprobaciones','logistics'=>'Logística','costs'=>'Costos','daily-reports'=>'Parte Diario','users'=>'Usuarios'][$permission] ?? $permission }}</span>@endforeach</div></td><td>{{ $user->last_access_at?->format('d/m/Y H:i') ?? 'Aún no ingresa' }}</td><td><span class="badge {{ $user->is_active?'aprobado':'rechazado' }}">{{ $user->is_active?'Activo':'Inactivo' }}</span></td><td><button onclick="event.preventDefault();document.getElementById('edit-user-{{ $user->id }}').showModal()">Editar</button></td></tr>
        <dialog id="edit-user-{{ $user->id }}"><form method="post" action="{{ route('users.update',$user) }}">@csrf @method('PUT')<div class="modal-head"><h2>Editar usuario</h2><button type="button" data-close>×</button></div>@include('users.form',['user'=>$user])<div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">Guardar cambios</button></div></form></dialog>
    @endforeach
    </tbody></table></div>{{ $users->links() }}
</div>
<dialog id="new-user"><form method="post" action="{{ route('users.store') }}">@csrf<div class="modal-head"><div><h2>Nuevo usuario</h2><p>Datos de acceso y permisos.</p></div><button type="button" data-close>×</button></div>@include('users.form',['user'=>null])<div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">Crear usuario</button></div></form></dialog>
@endsection
