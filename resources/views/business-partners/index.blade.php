@extends('layouts.app')
@section('title','Clientes y Proveedores')
@section('content')
<div class="breadcrumb">LOGÍSTICA › Clientes y Proveedores</div>
<div class="heading">
    <div><h1>Clientes y proveedores</h1><p>Consulta DNI o RUC y administra tus contactos comerciales.</p></div>
    <div class="heading-actions"><button class="secondary" type="button" onclick="document.getElementById('bank-management').showModal()">▤ Bancos</button><button class="primary" onclick="document.getElementById('new-partner').showModal()">＋ Nuevo registro</button></div>
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
        @forelse($partners as $partner)<tr><td><code>{{ $partner->document_type }} {{ $partner->document_number }}</code></td><td><div class="entity"><span>{{ mb_substr($partner->name,0,1) }}</span><div><strong>{{ $partner->name }}</strong><small>{{ $partner->trade_name ?: 'Sin nombre comercial' }}</small></div></div></td><td><em>{{ $partner->type }}</em></td><td>{{ $partner->address ?: '—' }}<br><small>{{ collect([$partner->district,$partner->province,$partner->department])->filter()->join(' · ') }}</small></td><td>{{ $partner->phone ?: '—' }}<br><small>{{ $partner->email }}</small></td><td><span class="badge {{ $partner->is_active?'aprobado':'rechazado' }}">{{ $partner->is_active?'Activo':'Inactivo' }}</span></td><td><div class="row-actions"><button onclick="event.preventDefault();document.getElementById('edit-partner-{{ $partner->id }}').showModal()">Editar</button>@if(auth()->user()->isAdministrator())<form method="post" action="{{ route('business-partners.destroy',$partner) }}" onsubmit="return confirm('¿Eliminar definitivamente este cliente o proveedor?')">@csrf @method('DELETE')<button class="danger">Eliminar</button></form>@endif</div></td></tr>
        <dialog id="edit-partner-{{ $partner->id }}"><form method="post" action="{{ route('business-partners.update',$partner) }}">@csrf @method('PUT')<div class="modal-head"><h2>Editar cliente o proveedor</h2><button type="button" data-close>×</button></div>@include('business-partners.form',['partner'=>$partner,'lookup'=>false])<div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">Guardar cambios</button></div></form></dialog>
        @empty<tr><td colspan="7" class="empty-state">Aún no existen clientes o proveedores registrados.</td></tr>@endforelse
    </tbody></table></div>{{ $partners->links() }}
</div>
<dialog class="partner-dialog" id="new-partner"><form method="post" action="{{ route('business-partners.store') }}">@csrf
    <div class="modal-head product-modal-head"><span class="modal-icon">♙</span><div><h2>Nuevo cliente o proveedor</h2><p>Consulta los datos usando DNI o RUC.</p></div><button type="button" data-close>×</button></div>
    @include('business-partners.form',['partner'=>null,'lookup'=>true,'banks'=>$banks])
    <div class="modal-foot"><small class="partner-save-note">Verifica los datos obtenidos antes de guardar.</small><button type="button" data-close>Cancelar</button><button class="primary">Guardar registro</button></div>
</form></dialog>
<dialog class="bank-management-dialog" id="bank-management"><div class="modal-head product-modal-head"><span class="modal-icon">▤</span><div><h2>Registro de bancos</h2><p>Agrega los bancos disponibles para seleccionarlos al crear un cliente o proveedor. El código se genera automáticamente.</p></div><button type="button" data-close>×</button></div><section class="bank-only-panel"><h3>Bancos registrados</h3><form action="{{ route('banks.store') }}" class="bank-inline-form" id="bank-form">@csrf<input name="name" placeholder="Nombre del banco" required><button class="primary">＋ Agregar banco</button></form><div class="bank-list" id="bank-list">@forelse($banks as $bank)<div><strong>{{ $bank->name }}</strong><small>{{ $bank->code }}</small></div>@empty<p>Aún no hay bancos registrados.</p>@endforelse</div></section><div class="modal-foot"><button type="button" data-close>Cerrar</button></div></dialog>
@endsection
@push('scripts')
<script>
(() => {
    const dialog = document.getElementById('new-partner');
    document.getElementById('add-partner-bank-account')?.addEventListener('click', () => { const box=document.getElementById('partner-bank-accounts'), index=Number(box.dataset.next || 1), row=box.firstElementChild.cloneNode(true); row.querySelectorAll('[name]').forEach(input => { input.name=input.name.replace(/\[0\]/, `[${index}]`); input.value=''; }); box.append(row); box.dataset.next=index+1; });
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
    const management = document.getElementById('bank-management');
    const csrf = @json(csrf_token());
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const requestJson = async form => { const response = await fetch(form.action, {method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf},body:new FormData(form)}); const body=await response.json(); if(!response.ok) throw new Error(body.message || Object.values(body.errors || {}).flat()[0] || 'No se pudo guardar.'); return body; };
    management.querySelector('#bank-form').addEventListener('submit', async event => { event.preventDefault(); const form=event.currentTarget; try { const bank=await requestJson(form); const list=management.querySelector('#bank-list'); list.querySelector('p')?.remove(); list.insertAdjacentHTML('afterbegin',`<div><strong>${escape(bank.name)}</strong><small>${escape(bank.code)}</small></div>`); document.querySelectorAll('#partner-bank-accounts select[name$="[bank_id]"]').forEach(select=>select.insertAdjacentHTML('beforeend',`<option value="${bank.id}">${escape(bank.name)}</option>`)); form.reset(); } catch(error) { alert(error.message); } });
})();
</script>
@endpush
