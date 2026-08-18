@extends('layouts.app')
@section('title','Recepción de Productos')
@section('content')
<div class="breadcrumb">ALMACÉN › Recepción de Productos</div>
<div class="heading">
    <div><h1>Recepción de productos</h1><p>Registra los productos que ingresan al almacén y actualiza sus existencias.</p></div>
    <button class="primary" onclick="document.getElementById('new-reception').showModal()">＋ Nueva recepción</button>
</div>
<div class="stats">
    <article><span>↓</span><div><small>Recepciones</small><strong>{{ \App\Models\ProductReception::count() }}</strong></div></article>
    <article><span>▣</span><div><small>Unidades recibidas</small><strong>{{ \App\Models\ProductReceptionItem::sum('quantity') }}</strong></div></article>
    <article><span>◷</span><div><small>Recepciones de hoy</small><strong>{{ \App\Models\ProductReception::whereDate('received_at',today())->count() }}</strong></div></article>
    <article><span>✓</span><div><small>Productos con stock</small><strong>{{ \App\Models\Product::where('type','Producto')->where('stock','>',0)->count() }}</strong></div></article>
</div>
<div class="card">
    <form class="toolbar"><label>⌕ <input name="q" value="{{ request('q') }}" placeholder="Buscar código, proveedor o documento..."></label><button>Buscar</button></form>
    <div class="table-wrap"><table><thead><tr><th>Código</th><th>Fecha</th><th>Proveedor</th><th>Documentos</th><th>Almacén</th><th>Productos</th><th>Unidades</th><th>Recibido por</th>@if(auth()->user()->isAdministrator())<th></th>@endif</tr></thead><tbody>
        @forelse($receptions as $reception)
        <tr>
            <td><code>{{ $reception->code }}</code></td>
            <td>{{ $reception->received_at->format('d/m/Y') }}</td>
            <td><strong>{{ $reception->supplier ?: 'No indicado' }}</strong></td>
            <td><div class="reception-documents">
                @if($reception->guide_number || $reception->guide_file)<span>Guía: {{ $reception->guide_number ?: 'S/N' }} @if($reception->guide_file)<a href="{{ \Illuminate\Support\Facades\Storage::url($reception->guide_file) }}" target="_blank">Ver archivo</a>@endif</span>@endif
                @if($reception->invoice_number || $reception->invoice_file)<span>Factura: {{ $reception->invoice_number ?: 'S/N' }} @if($reception->invoice_file)<a href="{{ \Illuminate\Support\Facades\Storage::url($reception->invoice_file) }}" target="_blank">Ver archivo</a>@endif</span>@endif
                @if($reception->order_number || $reception->order_file)<span>Orden: {{ $reception->order_number ?: 'S/N' }} @if($reception->order_file)<a href="{{ \Illuminate\Support\Facades\Storage::url($reception->order_file) }}" target="_blank">Ver archivo</a>@endif</span>@endif
                @if(! $reception->guide_number && ! $reception->guide_file && ! $reception->invoice_number && ! $reception->invoice_file && ! $reception->order_number && ! $reception->order_file)<span>—</span>@endif
            </div></td>
            <td><em>{{ $reception->warehouse }}</em></td>
            <td title="{{ $reception->items->pluck('product_name')->join(', ') }}">{{ $reception->items->count() }}</td>
            <td><strong>+{{ $reception->items->sum('quantity') }}</strong></td>
            <td>{{ $reception->receiver?->name ?? 'Usuario retirado' }}</td>
            @if(auth()->user()->isAdministrator())<td><form method="post" action="{{ route('product-receptions.destroy',$reception) }}" onsubmit="return confirm('¿Eliminar esta recepción y revertir su movimiento de stock?')">@csrf @method('DELETE')<button class="danger">Eliminar</button></form></td>@endif
        </tr>
        @empty
        <tr><td colspan="{{ auth()->user()->isAdministrator()?9:8 }}" class="empty-state">Aún no hay recepciones registradas.</td></tr>
        @endforelse
    </tbody></table></div>{{ $receptions->links() }}
</div>

<dialog class="reception-dialog" id="new-reception"><form method="post" enctype="multipart/form-data" action="{{ route('product-receptions.store') }}">@csrf
    <div class="modal-head product-modal-head"><span class="modal-icon">↓</span><div><h2>Nueva recepción de productos</h2><p>Las cantidades ingresadas se sumarán al stock actual.</p></div><strong class="reception-correlative">{{ $nextCode }}</strong><button type="button" data-close>×</button></div>
    <div class="reception-form">
        <div class="form-section-title"><strong>Datos de recepción</strong><span>Información del ingreso al almacén</span></div>
        <label class="rec-span-4">Fecha de recepción *<input type="date" name="received_at" value="{{ old('received_at',date('Y-m-d')) }}" required></label>
        <label class="rec-span-4">Proveedor
            <input name="supplier" list="registered-suppliers" value="{{ old('supplier') }}" placeholder="Escribe para buscar proveedor..." autocomplete="off">
            <datalist id="registered-suppliers">@foreach($suppliers as $supplier)<option value="{{ $supplier->name }}">{{ $supplier->document_number }}{{ $supplier->trade_name ? ' · '.$supplier->trade_name : '' }}</option>@endforeach</datalist>
            <small class="supplier-help">{{ $suppliers->count() }} proveedor(es) activo(s) registrado(s)</small>
        </label>
        <label class="rec-span-4">Almacén *<select name="warehouse" required><option>Almacén principal</option></select></label>
        <label class="rec-span-8">Observaciones<textarea name="notes" rows="2" maxlength="1000" placeholder="Estado de entrega u observaciones">{{ old('notes') }}</textarea></label>

        <div class="form-section-title reception-doc-title"><strong>Documentos de sustento</strong><span>Formatos permitidos: PDF, JPG y PNG, hasta 10 MB por archivo</span></div>
        <div class="reception-document-row" data-document-type="guide" data-number-target="guide_number">
            <label>N.º GUÍA<input name="guide_number" value="{{ old('guide_number') }}" placeholder="Número de guía"></label>
            <div class="document-actions">
                <label class="camera-action">📷 <span>Tomar foto</span><input class="document-analyzer" type="file" name="guide_camera" accept="image/*" capture="environment"></label>
                <label class="attach-action">📎 <span>Adjuntar guía</span><input class="document-analyzer" type="file" name="guide_file" accept=".pdf,.jpg,.jpeg,.png"></label>
            </div>
            <div class="analysis-status" aria-live="polite"></div>
        </div>
        <div class="reception-document-row" data-document-type="invoice" data-number-target="invoice_number">
            <label>N.º FACTURA<input name="invoice_number" value="{{ old('invoice_number') }}" placeholder="Número de factura"></label>
            <div class="document-actions">
                <label class="camera-action">📷 <span>Tomar foto</span><input class="document-analyzer" type="file" name="invoice_camera" accept="image/*" capture="environment"></label>
                <label class="attach-action">📎 <span>Adjuntar factura</span><input class="document-analyzer" type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png"></label>
            </div>
            <div class="analysis-status" aria-live="polite"></div>
        </div>
        <div class="reception-document-row" data-document-type="order" data-number-target="order_number">
            <label>N.º DE ORDEN<input name="order_number" value="{{ old('order_number') }}" placeholder="Número de orden"></label>
            <div class="document-actions">
                <label class="camera-action">📷 <span>Tomar foto</span><input class="document-analyzer" type="file" name="order_camera" accept="image/*" capture="environment"></label>
                <label class="attach-action">📎 <span>Adjuntar orden</span><input class="document-analyzer" type="file" name="order_file" accept=".pdf,.jpg,.jpeg,.png"></label>
            </div>
            <div class="analysis-status" aria-live="polite"></div>
        </div>

        <div class="requested-products-title"><div><strong>Productos recibidos</strong><span class="reception-item-count">1 ítem</span><small>Solo se muestran productos activos</small></div><button class="secondary add-reception-item" type="button">＋ Agregar producto</button></div>
        <div class="reception-items-wrap">
            <div class="reception-item-head"><span>N°</span><span>Producto</span><span>Código</span><span>Stock actual</span><span>Unidad</span><span>Cantidad recibida</span><span>Stock resultante</span><span></span></div>
            <div class="reception-items">
                <div class="reception-item-row">
                    <span class="row-number">1</span>
                    <select class="reception-product" name="items[0][product_id]" required><option value="">Seleccionar producto...</option>@foreach($products as $product)<option value="{{ $product->id }}" data-code="{{ $product->code }}" data-stock="{{ $product->stock }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>@endforeach</select>
                    <input class="reception-code" value="—" readonly><input class="reception-stock" value="0" readonly><input class="reception-unit" value="—" readonly>
                    <input class="reception-quantity" type="number" min="1" step="1" name="items[0][quantity]" value="1" required>
                    <input class="reception-result" value="1" readonly><button class="remove-reception-item" type="button" title="Quitar">×</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-foot requirement-foot"><small>Al guardar, el stock se actualizará de forma inmediata y quedará registrado el historial.</small><button type="button" data-close>Cancelar</button><button class="primary">Confirmar recepción</button></div>
</form></dialog>

<template id="reception-item-template"><div class="reception-item-row">
    <span class="row-number">__NUMBER__</span>
    <select class="reception-product" name="items[__INDEX__][product_id]" required><option value="">Seleccionar producto...</option>@foreach($products as $product)<option value="{{ $product->id }}" data-code="{{ $product->code }}" data-stock="{{ $product->stock }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>@endforeach</select>
    <input class="reception-code" value="—" readonly><input class="reception-stock" value="0" readonly><input class="reception-unit" value="—" readonly>
    <input class="reception-quantity" type="number" min="1" step="1" name="items[__INDEX__][quantity]" value="1" required>
    <input class="reception-result" value="1" readonly><button class="remove-reception-item" type="button" title="Quitar">×</button>
</div></template>
@endsection
@push('scripts')
<script>
(() => {
    const dialog = document.getElementById('new-reception');
    const rows = dialog.querySelector('.reception-items');
    const template = document.getElementById('reception-item-template');
    const count = dialog.querySelector('.reception-item-count');
    let nextIndex = 1;

    const refresh = () => {
        const current = [...rows.querySelectorAll('.reception-item-row')];
        current.forEach((row, index) => row.querySelector('.row-number').textContent = index + 1);
        count.textContent = `${current.length} ${current.length === 1 ? 'ítem' : 'ítems'}`;
        current.forEach(row => row.querySelector('.remove-reception-item').disabled = current.length === 1);
    };
    const calculate = row => {
        const option = row.querySelector('.reception-product').selectedOptions[0];
        const stock = Number(option?.dataset.stock || 0);
        const quantity = Number(row.querySelector('.reception-quantity').value || 0);
        row.querySelector('.reception-code').value = option?.dataset.code || '—';
        row.querySelector('.reception-stock').value = stock;
        row.querySelector('.reception-unit').value = option?.dataset.unit || '—';
        row.querySelector('.reception-result').value = stock + quantity;
    };
    const bind = row => {
        row.querySelector('.reception-product').addEventListener('change', () => calculate(row));
        row.querySelector('.reception-quantity').addEventListener('input', () => calculate(row));
        row.querySelector('.remove-reception-item').addEventListener('click', () => { row.remove(); refresh(); });
        calculate(row);
    };
    const addRow = () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', nextIndex).replaceAll('__NUMBER__', rows.children.length + 1).trim();
        const row = wrapper.firstElementChild;
        nextIndex++;
        rows.appendChild(row);
        bind(row);
        refresh();
        return row;
    };
    bind(rows.querySelector('.reception-item-row'));
    dialog.querySelector('.add-reception-item').addEventListener('click', addRow);

    const fillItems = items => {
        const matched = items.filter(item => item.matched && item.product_id);
        if (!matched.length) return 0;
        rows.innerHTML = '';
        matched.forEach(item => {
            const row = addRow();
            const select = row.querySelector('.reception-product');
            select.value = String(item.product_id);
            row.querySelector('.reception-quantity').value = item.quantity;
            calculate(row);
            if (item.confidence < .75) row.classList.add('needs-review');
        });
        refresh();
        return matched.length;
    };

    dialog.querySelectorAll('.document-analyzer').forEach(input => input.addEventListener('change', async () => {
        if (!input.files.length) return;
        const card = input.closest('.reception-document-row');
        const status = card.querySelector('.analysis-status');
        const otherInput = card.querySelector(`.document-analyzer:not([name="${input.name}"])`);
        if (otherInput) otherInput.value = '';
        status.className = 'analysis-status analyzing';
        status.textContent = 'Analizando documento con OpenAI…';
        const body = new FormData();
        body.append('document', input.files[0]);
        try {
            const response = await fetch(@json(route('product-receptions.analyze-document')), {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json'},
                body,
            });
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error(response.status === 413
                    ? 'El archivo supera el tamaño permitido por el servidor.'
                    : 'El servidor no pudo procesar el documento. Revisa el registro de errores.');
            }
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'No se pudo analizar.');
            if (result.document_number) dialog.querySelector(`[name="${card.dataset.numberTarget}"]`).value = result.document_number;
            const filled = fillItems(result.items || []);
            const pending = (result.items || []).filter(item => !item.matched).length;
            status.className = 'analysis-status success';
            status.textContent = `✓ ${filled} producto(s) completado(s)${pending ? ` · ${pending} sin coincidencia` : ''}. Revisa antes de confirmar.`;
        } catch (error) {
            status.className = 'analysis-status error';
            status.textContent = error.message;
        }
    }));
    refresh();
})();
</script>
@endpush
