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
    <article><span>✓</span><div><small>Aprobados total</small><strong>{{ \App\Models\Requirement::whereIn('status',['Aprobado','Aprobado total'])->count() }}</strong></div></article>
    <article><span>◐</span><div><small>Aprobados parcial</small><strong>{{ \App\Models\Requirement::whereIn('status',['Parcial','Aprobado parcial'])->count() }}</strong></div></article>
</div>
<div class="card">
    <form class="toolbar"><label>⌕ <input name="q" value="{{ request('q') }}" placeholder="Buscar código, responsable o proyecto..."></label><button>Buscar</button></form>
    <div class="table-wrap"><table><thead><tr><th>Código</th><th>Fecha</th><th>Responsable</th><th>Proyecto</th><th>Área</th><th>Ítems</th><th>Prioridad</th><th>Estado</th><th></th></tr></thead><tbody>
        @foreach($requirements as $item)
            <tr>
                <td><code>{{ $item->code }}</code></td>
                <td>{{ $item->requested_at->format('d/m/Y') }}</td>
                <td><strong>{{ $item->responsible }}</strong></td>
                <td>{{ $item->project }}</td>
                <td><em>{{ $item->area }}</em></td>
                <td>{{ $item->items->count() }}</td>
                <td>{{ $item->priority }}</td>
                <td><span class="badge {{ \Illuminate\Support\Str::slug($item->status) }}">{{ $item->status }}</span></td>
                <td>
                    <div class="row-actions">
                        @if($item->status === 'Pendiente')
                            <button type="button" onclick="document.getElementById('edit-requirement-{{ $item->id }}').showModal()">Editar</button>
                        @endif
                        @if(auth()->user()->isAdministrator())
                            <form method="post" action="{{ route('requirements.destroy', $item) }}" onsubmit="return confirm('¿Eliminar definitivamente este requerimiento, su aprobación y sus cotizaciones?')">
                                @csrf
                                @method('DELETE')
                                <button class="danger">Eliminar</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody></table></div>{{ $requirements->links() }}
</div>

@foreach($requirements as $requirement)
@if($requirement->status === 'Pendiente')
<dialog class="requirement-dialog requirement-edit-dialog" id="edit-requirement-{{ $requirement->id }}"><form method="post" enctype="multipart/form-data" action="{{ route('requirements.update', $requirement) }}">@csrf @method('PUT')
    <div class="modal-head product-modal-head"><span class="modal-icon">✎</span><div><h2>Editar requerimiento {{ $requirement->code }}</h2><p>Disponible únicamente mientras el requerimiento esté pendiente.</p></div><button type="button" data-close>×</button></div>
    <div class="requirement-form"><div class="form-section-title"><strong>Información general</strong><span>Los cambios quedarán en la misma solicitud pendiente.</span></div>
        <label class="req-span-4">Fecha *<input type="date" name="requested_at" value="{{ $requirement->requested_at->format('Y-m-d') }}" required></label>
        <label class="req-span-4">Responsable *<select name="responsible" required>@foreach($responsibles as $responsible)<option value="{{ $responsible->name }}" @selected($requirement->responsible === $responsible->name)>{{ $responsible->name }}</option>@endforeach</select></label>
        <label class="req-span-4">Mina / proyecto *<select name="project" required>@foreach($projects as $project)<option value="{{ $project->name }}" @selected($requirement->project === $project->name)>{{ $project->name }}</option>@endforeach</select></label>
        <label class="req-span-4">Área solicitante *<select name="area" required>@foreach($areas as $area)<option value="{{ $area->name }}" @selected($requirement->area === $area->name)>{{ $area->name }}</option>@endforeach</select></label>
        <div class="requested-products-title"><div><strong>Ítems solicitados</strong><small>Para mantener trazabilidad, no se agregan ni retiran filas; puedes editar sus datos.</small></div></div>
        <div class="requirement-edit-items">@foreach($requirement->items as $index => $item)<section><input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}"><b>{{ $index + 1 }}</b><label>Producto *<select name="items[{{ $index }}][product_id]" required>@foreach($products as $product)<option value="{{ $product->id }}" @selected($item->product_id === $product->id)>{{ $product->code }} · {{ $product->name }}</option>@endforeach</select></label><label>Centro de costos *<select name="items[{{ $index }}][cost_center_id]" required>@foreach($costCenters as $costCenter)<option value="{{ $costCenter->id }}" @selected($item->cost_center_id === $costCenter->id)>{{ $costCenter->parent->name }} · {{ $costCenter->name }}</option>@endforeach</select></label><label>Cantidad *<input type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]" value="{{ $item->quantity }}" required></label><label>Prioridad *<select name="items[{{ $index }}][priority]">@foreach(['Alta','Media','Baja'] as $priority)<option @selected($item->priority === $priority)>{{ $priority }}</option>@endforeach</select></label><label class="edit-description">Descripción<input name="items[{{ $index }}][description]" value="{{ $item->description }}"></label><label>Reemplazar imagen<input type="file" name="items[{{ $index }}][image]" accept="image/jpeg,image/png,image/webp"></label></section>@endforeach</div>
    </div><div class="modal-foot"><small>Una vez aprobado, este requerimiento quedará bloqueado para edición.</small><button type="button" data-close>Cancelar</button><button class="primary">Guardar cambios</button></div>
</form></dialog>
@endif
@endforeach

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
        <div class="form-grid"><label>Nombre del proyecto *<input name="name" required></label><label>Descripción<input name="description"></label></div>
        <div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">＋ Registrar proyecto</button></div>
    </form>
    <div class="manage-list">
        @forelse($projects as $project)
            <div><span class="entity"><span>{{ mb_substr($project->name,0,1) }}</span><span><strong>{{ $project->name }}</strong><small>{{ $project->code ?: 'Sin código' }}{{ $project->description ? ' · '.$project->description : '' }}</small></span></span><form method="post" action="{{ route('projects.destroy',$project) }}">@csrf @method('DELETE')<button class="danger">Retirar</button></form></div>
        @empty <p>Aún no existen proyectos registrados.</p> @endforelse
    </div>
</dialog>

<dialog class="requirement-dialog" id="new-requirement"><form method="post" enctype="multipart/form-data" action="{{ route('requirements.store') }}">@csrf
    <div class="modal-head product-modal-head"><span class="modal-icon">▤</span><div><h2>Nuevo requerimiento</h2><p>Ingresa los datos generales y selecciona productos registrados.</p></div><button type="button" data-close>×</button></div>
    <div class="requirement-form">
        <div class="form-section-title"><strong>Información del requerimiento</strong><span>Datos del solicitante y destino</span></div>
        <label class="req-span-8">Responsable *<select name="responsible" id="requirement-responsible" required><option value="">Seleccionar responsable</option>@foreach($responsibles as $responsible)<option value="{{ $responsible->name }}">{{ $responsible->name }}{{ $responsible->position ? ' · '.$responsible->position : '' }}</option>@endforeach<option value="__new__">＋ Agregar nuevo responsable</option></select></label>
        <label class="req-span-4">Fecha *<input type="date" name="requested_at" value="{{ date('Y-m-d') }}" required></label>
        <label class="req-span-4">Mina / proyecto *<select name="project" required><option value="">Seleccionar proyecto</option>@foreach($projects as $project)<option value="{{ $project->name }}" {{ $project->name === 'MINA CAROLINA JE' ? 'selected' : '' }}>{{ $project->name }}{{ $project->code ? ' · '.$project->code : '' }}</option>@endforeach</select></label>
        <label class="req-span-4">Área solicitante *<select name="area" id="requirement-area" required><option value="">Seleccionar área</option>@foreach($areas as $area)<option value="{{ $area->name }}">{{ $area->name }}</option>@endforeach<option value="__new__">＋ Agregar nueva área</option></select></label>

        <div class="requested-products-title"><div><strong>Productos solicitados</strong><span class="item-count">1 ítem</span><small>Busca entre {{ $products->count() }} productos registrados</small></div><button class="secondary add-item" type="button">＋ Agregar fila</button></div>
        <div class="requirement-items-wrap">
            <div class="requirement-item-head"><span>N°</span><span>Rubro</span><span>Producto registrado</span><span></span><span>Centro de costos</span><span>Descripción</span><span>Foto / imagen</span><span>Cantidad</span><span>Unidad</span><span>Prioridad</span><span></span></div>
            <div class="requirement-items">
                <div class="requirement-item-row">
                    <span class="row-number">1</span><input class="item-category" value="Automático" readonly>
                    <select class="item-product" name="items[0][product_id]" required><option value="">Buscar producto...</option>@foreach($products as $product)<option value="{{ $product->id }}" data-category="{{ $product->category ?: 'Sin rubro' }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>@endforeach</select>
                    <button class="new-product-inline" type="button" title="Crear producto">＋</button>
                    <select name="items[0][cost_center_id]" required><option value="">Seleccionar centro...</option>@foreach($costCenters as $costCenter)<option value="{{ $costCenter->id }}">{{ $costCenter->parent->name }} · {{ $costCenter->name }}</option>@endforeach</select>
                    <input name="items[0][description]" placeholder="Detalle o especificación">
                    <label class="item-image-picker"><input type="file" name="items[0][image]" accept="image/jpeg,image/png,image/webp"><span>▧ Adjuntar imagen</span><small>JPG, PNG o WEBP · máx. 5 MB</small></label>
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
    <button class="new-product-inline" type="button" title="Crear producto">＋</button><select name="items[__INDEX__][cost_center_id]" required><option value="">Seleccionar centro...</option>@foreach($costCenters as $costCenter)<option value="{{ $costCenter->id }}">{{ $costCenter->parent->name }} · {{ $costCenter->name }}</option>@endforeach</select><input name="items[__INDEX__][description]" placeholder="Detalle o especificación"><label class="item-image-picker"><input type="file" name="items[__INDEX__][image]" accept="image/jpeg,image/png,image/webp"><span>▧ Adjuntar imagen</span><small>JPG, PNG o WEBP · máx. 5 MB</small></label><input type="number" step="0.01" min="0.01" name="items[__INDEX__][quantity]" value="1" required><input class="item-unit" value="Unidad" readonly><select name="items[__INDEX__][priority]" required><option>Alta</option><option selected>Media</option><option>Baja</option></select><button class="remove-item" type="button" title="Quitar fila">×</button>
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
        row.querySelector('.item-image-picker input').addEventListener('change', event => {
            const label = row.querySelector('.item-image-picker span');
            label.textContent = event.target.files[0] ? `▧ ${event.target.files[0].name}` : '▧ Adjuntar imagen';
        });
    };
    bind(rows.querySelector('.requirement-item-row'));
    document.getElementById('requirement-responsible').addEventListener('change', event => { if (event.target.value === '__new__') { event.target.value = ''; document.getElementById('responsibles').showModal(); } });
    document.getElementById('requirement-area').addEventListener('change', event => { if (event.target.value === '__new__') { event.target.value = ''; document.getElementById('areas').showModal(); } });
    dialog.querySelector('.add-item').addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', nextIndex).replaceAll('__NUMBER__', rows.children.length + 1).trim();
        const row = wrapper.firstElementChild; nextIndex++; rows.appendChild(row); bind(row); refresh();
    });
    refresh();

    const productGroups = @json($groupData);
    const productForm = document.querySelector('#new-product-from-requirement .product-form');
    if (productForm) {
        const group = productForm.querySelector('.product-group-select');
        const subgroup = productForm.querySelector('.product-subgroup-select');
        const category = productForm.querySelector('.product-category-value');
        const refreshSubgroups = () => {
            const selected = subgroup.dataset.selected;
            subgroup.innerHTML = '<option value="">Sin subgrupo</option>';
            (productGroups.find(item => String(item.id) === group.value)?.subgroups || []).forEach(item => subgroup.add(new Option(`${item.code} · ${item.name}`, item.id, false, String(item.id) === String(selected))));
            category.value = subgroup.selectedOptions[0]?.text?.replace(/^.* · /, '') || '';
            subgroup.dataset.selected = '';
        };
        const toggleOperation = () => {
            const purchase = productForm.querySelector('[name="operation_type"]:checked')?.value === 'Compra';
            productForm.querySelectorAll('.sales-only').forEach(field => { field.hidden = purchase; field.querySelectorAll('input,select').forEach(input => input.disabled = purchase); });
        };
        group.addEventListener('change', refreshSubgroups);
        subgroup.addEventListener('change', () => { category.value = subgroup.selectedOptions[0]?.text?.replace(/^.* · /, '') || ''; });
        productForm.querySelectorAll('[name="operation_type"]').forEach(input => input.addEventListener('change', toggleOperation));
        refreshSubgroups(); toggleOperation();
    }
})();
</script>
@endpush
