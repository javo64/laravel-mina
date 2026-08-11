@extends('layouts.app')
@section('title','Requerimientos')
@section('content')
<div class="breadcrumb">ALMACÉN › Requerimientos</div>
<div class="heading">
    <div><h1>Requerimientos</h1><p>Registra y da seguimiento a solicitudes internas.</p></div>
    <div class="heading-actions">
        <button class="secondary" onclick="document.getElementById('responsibles').showModal()">♙ Responsables</button>
        <button class="secondary" onclick="document.getElementById('areas').showModal()">▦ Áreas</button>
        <button class="secondary" onclick="document.getElementById('projects').showModal()">◇ Proyectos</button>
        <button class="primary" onclick="document.getElementById('new-requirement').showModal()">＋ Nuevo requerimiento</button>
    </div>
</div>
<div class="stats">
    <article><span>▤</span><div><small>Total</small><strong>{{ \App\Models\Requirement::count() }}</strong></div></article>
    <article><span>◷</span><div><small>Pendientes</small><strong>{{ \App\Models\Requirement::where('status','Pendiente')->count() }}</strong></div></article>
    <article><span>✓</span><div><small>Aprobados</small><strong>{{ \App\Models\Requirement::where('status','Aprobado')->count() }}</strong></div></article>
    <article><span>×</span><div><small>Rechazados</small><strong>{{ \App\Models\Requirement::where('status','Rechazado')->count() }}</strong></div></article>
</div>
<div class="card">
    <form class="toolbar"><label>⌕ <input name="q" value="{{ request('q') }}" placeholder="Buscar código, responsable o proyecto..."></label><button>Buscar</button></form>
    <div class="table-wrap"><table><thead><tr><th>Código</th><th>Fecha</th><th>Responsable</th><th>Proyecto</th><th>Área</th><th>Ítems</th><th>Prioridad</th><th>Estado</th></tr></thead><tbody>
        @foreach($requirements as $item)<tr><td><code>{{ $item->code }}</code></td><td>{{ $item->requested_at->format('d/m/Y') }}</td><td><strong>{{ $item->responsible }}</strong></td><td>{{ $item->project }}</td><td><em>{{ $item->area }}</em></td><td>{{ $item->items->count() }}</td><td>{{ $item->priority }}</td><td><span class="badge {{ strtolower($item->status) }}">{{ $item->status }}</span></td></tr>@endforeach
    </tbody></table></div>{{ $requirements->links() }}
</div>

<dialog id="responsibles"><div class="modal-head"><div><h2>Registro de responsables</h2><p>Administra las personas que pueden solicitar requerimientos.</p></div><button type="button" data-close>×</button></div>
    <form method="post" action="{{ route('responsibles.store') }}">@csrf
        <div class="form-grid"><label>Nombre completo *<input name="name" required></label><label>Cargo<input name="position"></label><label>Correo electrónico<input type="email" name="email"></label></div>
        <div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">＋ Registrar responsable</button></div>
    </form>
    <div class="manage-list">
        @forelse($responsibles as $responsible)
            <div><span class="entity"><span>{{ mb_substr($responsible->name,0,1) }}</span><span><strong>{{ $responsible->name }}</strong><small>{{ $responsible->position ?: 'Sin cargo' }}{{ $responsible->email ? ' · '.$responsible->email : '' }}</small></span></span><form method="post" action="{{ route('responsibles.destroy',$responsible) }}">@csrf @method('DELETE')<button class="danger">Retirar</button></form></div>
        @empty <p>Aún no existen responsables registrados.</p> @endforelse
    </div>
</dialog>

<dialog id="areas"><div class="modal-head"><div><h2>Registro de áreas</h2><p>Administra las áreas que pueden generar requerimientos.</p></div><button type="button" data-close>×</button></div>
    <form method="post" action="{{ route('areas.store') }}">@csrf
        <div class="form-grid"><label>Nombre del área *<input name="name" required></label><label>Descripción<input name="description"></label></div>
        <div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">＋ Registrar área</button></div>
    </form>
    <div class="manage-list">
        @forelse($areas as $area)
            <div><span class="entity"><span>{{ mb_substr($area->name,0,1) }}</span><span><strong>{{ $area->name }}</strong><small>{{ $area->description ?: 'Sin descripción' }}</small></span></span><form method="post" action="{{ route('areas.destroy',$area) }}">@csrf @method('DELETE')<button class="danger">Retirar</button></form></div>
        @empty <p>Aún no existen áreas registradas.</p> @endforelse
    </div>
</dialog>

<dialog id="projects"><div class="modal-head"><div><h2>Registro de proyectos</h2><p>Administra los proyectos asociados a los requerimientos.</p></div><button type="button" data-close>×</button></div>
    <form method="post" action="{{ route('projects.store') }}">@csrf
        <div class="form-grid"><label>Nombre del proyecto *<input name="name" required></label><label>Código<input name="code"></label><label>Descripción<input name="description"></label></div>
        <div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">＋ Registrar proyecto</button></div>
    </form>
    <div class="manage-list">
        @forelse($projects as $project)
            <div><span class="entity"><span>{{ mb_substr($project->name,0,1) }}</span><span><strong>{{ $project->name }}</strong><small>{{ $project->code ?: 'Sin código' }}{{ $project->description ? ' · '.$project->description : '' }}</small></span></span><form method="post" action="{{ route('projects.destroy',$project) }}">@csrf @method('DELETE')<button class="danger">Retirar</button></form></div>
        @empty <p>Aún no existen proyectos registrados.</p> @endforelse
    </div>
</dialog>

<dialog class="requirement-dialog" id="new-requirement"><form method="post" action="{{ route('requirements.store') }}">@csrf
    <div class="modal-head product-modal-head"><span class="modal-icon">▤</span><div><h2>Nuevo requerimiento</h2><p>Ingresa los datos generales y selecciona productos registrados.</p></div><button type="button" data-close>×</button></div>
    <div class="requirement-form">
        <div class="form-section-title"><strong>Información del requerimiento</strong><span>Datos del solicitante y destino</span></div>
        <label class="req-span-8">Responsable *<select name="responsible" required><option value="">Seleccionar responsable</option>@foreach($responsibles as $responsible)<option value="{{ $responsible->name }}">{{ $responsible->name }}{{ $responsible->position ? ' · '.$responsible->position : '' }}</option>@endforeach</select></label>
        <label class="req-span-4">Fecha *<input type="date" name="requested_at" value="{{ date('Y-m-d') }}" required></label>
        <label class="req-span-4">Mina / proyecto *<select name="project" required><option value="">Seleccionar proyecto</option>@foreach($projects as $project)<option value="{{ $project->name }}">{{ $project->name }}{{ $project->code ? ' · '.$project->code : '' }}</option>@endforeach</select></label>
        <label class="req-span-4">Área solicitante *<select name="area" required><option value="">Seleccionar área</option>@foreach($areas as $area)<option value="{{ $area->name }}">{{ $area->name }}</option>@endforeach</select></label>

        <div class="requested-products-title"><div><strong>Productos solicitados</strong><span class="item-count">1 ítem</span><small>Busca entre {{ $products->count() }} productos registrados</small></div><button class="secondary add-item" type="button">＋ Agregar fila</button></div>
        <div class="requirement-items-wrap">
            <div class="requirement-item-head"><span>N°</span><span>Rubro</span><span>Producto registrado</span><span></span><span>Descripción</span><span>Cantidad</span><span>Unidad</span><span>Prioridad</span><span></span></div>
            <div class="requirement-items">
                <div class="requirement-item-row">
                    <span class="row-number">1</span><input class="item-category" value="Automático" readonly>
                    <select class="item-product" name="items[0][product_id]" required><option value="">Buscar producto...</option>@foreach($products as $product)<option value="{{ $product->id }}" data-category="{{ $product->category ?: 'Sin rubro' }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>@endforeach</select>
                    <button class="new-product-inline" type="button" title="Crear producto">＋</button>
                    <input name="items[0][description]" placeholder="Detalle o especificación">
                    <input type="number" step="0.01" min="0.01" name="items[0][quantity]" value="1" required>
                    <input class="item-unit" value="Unidad" readonly>
                    <select name="items[0][priority]" required><option>Alta</option><option selected>Media</option><option>Baja</option></select>
                    <button class="remove-item" type="button" title="Quitar fila">×</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-foot requirement-foot"><small>¿No encuentras el producto? Usa el botón + junto al buscador para crearlo.</small><button type="button" data-close>Cancelar</button><button class="primary">Guardar como pendiente</button></div>
</form></dialog>

<template id="requirement-item-template"><div class="requirement-item-row">
    <span class="row-number">__NUMBER__</span><input class="item-category" value="Automático" readonly>
    <select class="item-product" name="items[__INDEX__][product_id]" required><option value="">Buscar producto...</option>@foreach($products as $product)<option value="{{ $product->id }}" data-category="{{ $product->category ?: 'Sin rubro' }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>@endforeach</select>
    <button class="new-product-inline" type="button" title="Crear producto">＋</button><input name="items[__INDEX__][description]" placeholder="Detalle o especificación"><input type="number" step="0.01" min="0.01" name="items[__INDEX__][quantity]" value="1" required><input class="item-unit" value="Unidad" readonly><select name="items[__INDEX__][priority]" required><option>Alta</option><option selected>Media</option><option>Baja</option></select><button class="remove-item" type="button" title="Quitar fila">×</button>
</div></template>

<dialog class="product-dialog" id="new-product-from-requirement"><form method="post" action="{{ route('products.store') }}">@csrf<div class="modal-head product-modal-head"><span class="modal-icon">▣</span><div><h2>Nuevo producto o servicio</h2><p>Al guardar se agregará al catálogo general.</p></div><button type="button" data-close>×</button></div>@include('products.form',['product'=>null])<div class="modal-foot"><small>Después de guardarlo podrás seleccionarlo en el requerimiento.</small><button type="button" data-close>Cancelar</button><button class="primary">Guardar producto</button></div></form></dialog>
@endsection
@push('scripts')
<script>
(() => {
    const dialog = document.getElementById('new-requirement');
    const rows = dialog.querySelector('.requirement-items');
    const template = document.getElementById('requirement-item-template');
    const count = dialog.querySelector('.item-count');
    let nextIndex = 1;

    const refresh = () => {
        const current = [...rows.querySelectorAll('.requirement-item-row')];
        current.forEach((row, index) => row.querySelector('.row-number').textContent = index + 1);
        count.textContent = `${current.length} ${current.length === 1 ? 'ítem' : 'ítems'}`;
        current.forEach(row => row.querySelector('.remove-item').disabled = current.length === 1);
    };
    const bind = row => {
        row.querySelector('.item-product').addEventListener('change', event => {
            const option = event.target.selectedOptions[0];
            row.querySelector('.item-category').value = option?.dataset.category || 'Automático';
            row.querySelector('.item-unit').value = option?.dataset.unit || 'Unidad';
        });
        row.querySelector('.remove-item').addEventListener('click', () => { row.remove(); refresh(); });
        row.querySelector('.new-product-inline').addEventListener('click', () => document.getElementById('new-product-from-requirement').showModal());
    };
    bind(rows.querySelector('.requirement-item-row'));
    dialog.querySelector('.add-item').addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', nextIndex).replaceAll('__NUMBER__', rows.children.length + 1).trim();
        const row = wrapper.firstElementChild; nextIndex++; rows.appendChild(row); bind(row); refresh();
    });
    refresh();
})();
</script>
@endpush
