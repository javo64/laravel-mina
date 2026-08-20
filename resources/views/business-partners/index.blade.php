@extends('layouts.app')
@section('title','Clientes y Proveedores')
@section('content')
<div class="breadcrumb">LOGÍSTICA › Clientes y Proveedores</div>
<div class="heading">
    <div><h1>Clientes y proveedores</h1><p>Consulta DNI o RUC y administra tus contactos comerciales.</p></div>
    <div class="heading-actions"><button class="secondary" type="button" onclick="document.getElementById('bank-management').showModal()">▤ Bancos y cuentas</button><button class="primary" onclick="document.getElementById('new-partner').showModal()">＋ Nuevo registro</button></div>
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
    @include('business-partners.form',['partner'=>null,'lookup'=>true])
    <div class="modal-foot"><small class="partner-save-note">Verifica los datos obtenidos antes de guardar.</small><button type="button" data-close>Cancelar</button><button class="primary">Guardar registro</button></div>
</form></dialog>
<dialog class="bank-management-dialog" id="bank-management"><div class="modal-head product-modal-head"><span class="modal-icon">▤</span><div><h2>Bancos y cuentas de proveedores</h2><p>Registra bancos padre y las cuentas en soles o dólares de cada proveedor.</p></div><button type="button" data-close>×</button></div><div class="bank-management-grid"><section><h3>Bancos</h3><form method="post" action="{{ route('banks.store') }}" class="bank-inline-form">@csrf<input name="name" placeholder="Nombre del banco" required><input name="code" placeholder="Código opcional"><button class="secondary">＋ Agregar banco</button></form><div class="bank-list">@forelse($banks as $bank)<div><strong>{{ $bank->name }}</strong><small>{{ $bank->code ?: 'Sin código' }} · {{ $bank->accounts_count }} cuenta(s)</small></div>@empty<p>Aún no hay bancos registrados.</p>@endforelse</div></section><section><h3>Cuentas de proveedores</h3><form method="post" action="{{ route('business-partners.bank-accounts.store') }}" class="bank-account-form">@csrf<label>Proveedor *<select name="business_partner_id" required><option value="">Seleccionar proveedor</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->document_number }} · {{ $supplier->name }}</option>@endforeach</select></label><label>Banco *<select name="bank_id" required><option value="">Seleccionar banco</option>@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }}</option>@endforeach</select></label><label>Tipo de cuenta *<select name="account_type"><option value="Cuenta Corriente">Cuenta Corriente</option><option value="Cuenta Interbancaria">Cuenta Interbancaria</option></select></label><label>Moneda *<select name="currency"><option value="PEN">Soles (PEN)</option><option value="USD">Dólares (USD)</option></select></label><label>Número de cuenta *<input name="account_number" required></label><label>Titular<input name="holder_name"></label><button class="primary">Guardar cuenta</button></form></section></div><div class="bank-accounts-table"><h3>Cuentas registradas</h3><div class="table-wrap"><table><thead><tr><th>Proveedor</th><th>Banco</th><th>Tipo</th><th>Moneda</th><th>Cuenta</th><th>Titular</th></tr></thead><tbody>@forelse($bankAccounts as $account)<tr><td>{{ $account->partner?->name ?: 'Sin proveedor' }}</td><td>{{ $account->bank?->name ?: $account->bank_name }}</td><td>{{ $account->account_type }}</td><td>{{ $account->currency === 'USD' ? 'Dólares' : 'Soles' }}</td><td><code>{{ $account->account_number }}</code></td><td>{{ $account->holder_name ?: '—' }}</td></tr>@empty<tr><td colspan="6">Aún no hay cuentas registradas.</td></tr>@endforelse</tbody></table></div></div><div class="modal-foot"><button type="button" data-close>Cerrar</button></div></dialog>
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
