@extends('layouts.app')
@section('title','Clientes y Proveedores')
@section('content')
<div class="breadcrumb">LOGÍSTICA › Clientes y Proveedores</div>
<div class="heading">
    <div><h1>Clientes y proveedores</h1><p>Consulta DNI o RUC y administra tus contactos comerciales.</p></div>
    <button class="primary" onclick="document.getElementById('new-partner').showModal()">＋ Nuevo registro</button>
</div>
<div class="stats">
    <article><span>♙</span><div><small>Clientes</small><strong>{{ \App\Models\BusinessPartner::whereIn('type',['Cliente','Cliente y proveedor'])->where('is_active',true)->count() }}</strong></div></article>
    <article><span>▣</span><div><small>Proveedores</small><strong>{{ \App\Models\BusinessPartner::whereIn('type',['Proveedor','Cliente y proveedor'])->where('is_active',true)->count() }}</strong></div></article>
    <article><span>◆</span><div><small>Con RUC</small><strong>{{ \App\Models\BusinessPartner::where('document_type','RUC')->count() }}</strong></div></article>
    <article><span>✓</span><div><small>Activos</small><strong>{{ \App\Models\BusinessPartner::where('is_active',true)->count() }}</strong></div></article>
</div>
<div class="partner-tabs"><a class="{{ !request('type')?'active':'' }}" href="{{ route('business-partners.index') }}">Todos</a><a class="{{ request('type')==='Cliente'?'active':'' }}" href="{{ route('business-partners.index',['type'=>'Cliente']) }}">Clientes</a><a class="{{ request('type')==='Proveedor'?'active':'' }}" href="{{ route('business-partners.index',['type'=>'Proveedor']) }}">Proveedores</a></div>
<div class="card">
    <form class="toolbar"><label>⌕ <input name="q" value="{{ request('q') }}" placeholder="Buscar documento, nombre o razón social..."></label>@if(request('type'))<input type="hidden" name="type" value="{{ request('type') }}">@endif<button>Buscar</button></form>
    <div class="table-wrap"><table><thead><tr><th>Documento</th><th>Nombre / Razón social</th><th>Tipo</th><th>Dirección</th><th>Contacto</th><th>Estado</th><th></th></tr></thead><tbody>
        @forelse($partners as $partner)<tr><td><code>{{ $partner->document_type }} {{ $partner->document_number }}</code></td><td><div class="entity"><span>{{ mb_substr($partner->name,0,1) }}</span><div><strong>{{ $partner->name }}</strong><small>{{ $partner->trade_name ?: 'Sin nombre comercial' }}</small></div></div></td><td><em>{{ $partner->type }}</em></td><td>{{ $partner->address ?: '—' }}<br><small>{{ collect([$partner->district,$partner->province,$partner->department])->filter()->join(' · ') }}</small></td><td>{{ $partner->phone ?: '—' }}<br><small>{{ $partner->email }}</small></td><td><span class="badge {{ $partner->is_active?'aprobado':'rechazado' }}">{{ $partner->is_active?'Activo':'Inactivo' }}</span></td><td><button onclick="event.preventDefault();document.getElementById('edit-partner-{{ $partner->id }}').showModal()">Editar</button></td></tr>
        <dialog id="edit-partner-{{ $partner->id }}"><form method="post" action="{{ route('business-partners.update',$partner) }}">@csrf @method('PUT')<div class="modal-head"><h2>Editar cliente o proveedor</h2><button type="button" data-close>×</button></div>@include('business-partners.form',['partner'=>$partner,'lookup'=>false])<div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">Guardar cambios</button></div></form></dialog>
        @empty<tr><td colspan="7" class="empty-state">Aún no existen clientes o proveedores registrados.</td></tr>@endforelse
    </tbody></table></div>{{ $partners->links() }}
</div>
<dialog class="partner-dialog" id="new-partner"><form method="post" action="{{ route('business-partners.store') }}">@csrf
    <div class="modal-head product-modal-head"><span class="modal-icon">♙</span><div><h2>Nuevo cliente o proveedor</h2><p>Consulta los datos usando DNI o RUC.</p></div><button type="button" data-close>×</button></div>
    @include('business-partners.form',['partner'=>null,'lookup'=>true])
    <div class="modal-foot"><small class="partner-save-note">Verifica los datos obtenidos antes de guardar.</small><button type="button" data-close>Cancelar</button><button class="primary">Guardar registro</button></div>
</form></dialog>
@endsection
@push('scripts')
<script>
(() => {
    const dialog = document.getElementById('new-partner');
    const documentInput = dialog.querySelector('[name="document_number"]');
    const status = dialog.querySelector('.lookup-status');
    dialog.querySelector('.lookup-document').addEventListener('click', async () => {
        const document = documentInput.value.replace(/\D/g,'');
        documentInput.value = document;
        if (![8,11].includes(document.length)) { status.className='lookup-status error'; status.textContent='Ingresa un DNI de 8 dígitos o un RUC de 11 dígitos.'; return; }
        status.className='lookup-status analyzing'; status.textContent='Consultando documento…';
        try {
            const response = await fetch(@json(route('business-partners.lookup')), {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},body:JSON.stringify({document_number:document})});
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) throw new Error('El servidor no pudo procesar la consulta. Revisa la configuración de la API.');
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'No se pudo consultar.');
            Object.entries(result).forEach(([key,value]) => { const field=dialog.querySelector(`[name="${key}"]`); if(field && value!==null) field.value=value; });
            status.className='lookup-status success'; status.textContent='✓ Datos encontrados. Revisa y completa la información de contacto.';
        } catch (error) { status.className='lookup-status error'; status.textContent=error.message; }
    });
})();
</script>
@endpush
