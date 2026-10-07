@extends('layouts.app')
@section('title','Recepciones de Mina')
@section('content')
@php
    $pendingOrders = $receivableOrders->where('receipt_status','Pendiente')->count();
    $partialOrders = $receivableOrders->where('receipt_status','Parcial')->count();
    $completeOrders = \App\Models\PurchaseOrder::where('document','OCO')->where('receipt_status','Completa')->count();
    $orderData = $receivableOrders->map(function ($order) {
        $lines = $order->items->filter(fn ($item) => $item->product?->type === 'Producto')
            ->map(function ($item) {
                $ordered = (float) $item->quantity;
                $received = (float) $item->received_quantity;
                return [
                    'id'=>$item->id, 'name'=>$item->product_name, 'code'=>$item->product?->code,
                    'unit'=>$item->unit, 'ordered'=>$ordered, 'received'=>$received,
                    'pending'=>max(0,$ordered-$received),
                ];
            })->filter(fn ($item) => $item['pending'] > 0)->values();
        return [
            'id'=>$order->id, 'code'=>$order->code, 'supplier'=>$order->supplier?->name ?? 'Proveedor retirado',
            'branch'=>$order->destination_branch, 'warehouse'=>$order->destination_warehouse,
            'status'=>$order->receipt_status, 'receptions'=>$order->receptions->count(), 'items'=>$lines,
        ];
    })->values();
@endphp

<div class="breadcrumb">ALMACÉN › Operaciones › Recepciones</div>
<div class="heading odoo-heading">
    <div><h1>Recepciones de mina</h1><p>Controla lo solicitado, recibido y pendiente contra cada orden de compra aprobada.</p></div>
    <button class="primary" id="open-general-reception" @disabled($receivableOrders->isEmpty())>＋ Nueva recepción</button>
</div>

<div class="mining-operation-stats">
    <article><span class="operation-icon waiting">⌛</span><div><small>Órdenes por recibir</small><strong>{{ $pendingOrders }}</strong></div></article>
    <article><span class="operation-icon partial">◐</span><div><small>Recepciones parciales</small><strong>{{ $partialOrders }}</strong></div></article>
    <article><span class="operation-icon done">✓</span><div><small>Órdenes completadas</small><strong>{{ $completeOrders }}</strong></div></article>
    <article><span class="operation-icon today">↓</span><div><small>Ingresos de hoy</small><strong>{{ \App\Models\ProductReception::whereDate('received_at',today())->count() }}</strong></div></article>
</div>

<section class="odoo-operation-board">
    <header><div><h2>Órdenes listas para recepción</h2><p>Solo aparecen órdenes de compra aprobadas con productos pendientes.</p></div><span>{{ $receivableOrders->count() }} operación(es)</span></header>
    <div class="receipt-order-grid">
        @forelse($receivableOrders as $order)
            @php
                $productItems = $order->items->filter(fn($item)=>$item->product?->type === 'Producto');
                $ordered = $productItems->sum(fn($item)=>(float)$item->quantity);
                $received = $productItems->sum(fn($item)=>(float)$item->received_quantity);
                $progress = $ordered > 0 ? min(100,round($received/$ordered*100)) : 0;
            @endphp
            <article class="receipt-order-card">
                <div class="receipt-order-card-top"><div><code>{{ $order->code }}</code><strong>{{ $order->supplier?->name ?? 'Proveedor retirado' }}</strong></div><span class="receipt-state {{ strtolower($order->receipt_status) }}">{{ $order->receipt_status }}</span></div>
                <dl><div><dt>Destino</dt><dd>{{ $order->destination_branch }} · {{ $order->destination_warehouse }}</dd></div><div><dt>Avance</dt><dd>{{ $received }} / {{ $ordered }} unidades</dd></div></dl>
                <div class="receipt-progress" role="progressbar" aria-label="Avance de recepción" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><i style="width:{{ $progress }}%"></i></div>
                <footer><span>{{ $order->receptions->count() }} recepción(es) · {{ $progress }}%</span><button type="button" class="secondary receive-order" data-order="{{ $order->id }}">Recibir productos</button></footer>
            </article>
        @empty
            <div class="empty-state receipt-empty"><b>✓</b><p>No existen órdenes aprobadas pendientes de recepción.</p><small>Las nuevas órdenes aparecerán aquí después de su aprobación.</small></div>
        @endforelse
    </div>
</section>

<div class="card receipt-history">
    <div class="receipt-history-head"><div><h2>Historial de transferencias de entrada</h2><p>Trazabilidad de cada recepción validada y su movimiento de inventario.</p></div></div>
    <form class="toolbar"><label>⌕ <input name="q" value="{{ request('q') }}" placeholder="Buscar recepción, orden o proveedor..."></label><button>Buscar</button></form>
    <div class="table-wrap"><table><thead><tr><th>Referencia</th><th>Orden origen</th><th>Fecha efectiva</th><th>Proveedor</th><th>Destino</th><th>Productos</th><th>Unidades</th><th>Responsable</th>@if(auth()->user()->isAdministrator())<th></th>@endif</tr></thead><tbody>
        @forelse($receptions as $reception)
        <tr>
            <td><code>{{ $reception->code }}</code><small class="table-subline">Validado</small></td>
            <td>@if($reception->purchaseOrder)<strong>{{ $reception->purchaseOrder->code }}</strong><small class="table-subline">{{ $reception->purchaseOrder->receipt_status }}</small>@else<span>Recepción manual</span>@endif</td>
            <td>{{ $reception->received_at->format('d/m/Y') }}</td>
            <td>{{ $reception->supplier ?: 'No indicado' }}</td>
            <td><span class="warehouse-destination">▦ {{ $reception->warehouse }}</span></td>
            <td title="{{ $reception->items->pluck('product_name')->join(', ') }}">{{ $reception->items->count() }} línea(s)</td>
            <td><strong>+{{ rtrim(rtrim(number_format($reception->items->sum('quantity'),2,'.',''),'0'),'.') }}</strong></td>
            <td>{{ $reception->receiver?->name ?? 'Usuario retirado' }}</td>
            @if(auth()->user()->isAdministrator())<td><form method="post" action="{{ route('product-receptions.destroy',$reception) }}" onsubmit="return confirm('¿Revertir esta recepción, su stock y el avance de la orden?')">@csrf @method('DELETE')<button class="danger">Revertir</button></form></td>@endif
        </tr>
        @empty<tr><td colspan="{{ auth()->user()->isAdministrator()?9:8 }}" class="empty-state">Aún no hay recepciones registradas.</td></tr>@endforelse
    </tbody></table></div>{{ $receptions->links() }}
</div>

<dialog class="reception-dialog odoo-reception-dialog" id="new-reception"><form method="post" enctype="multipart/form-data" action="{{ route('product-receptions.store') }}">@csrf
    <div class="modal-head product-modal-head"><span class="modal-icon">↓</span><div><h2>Recepción de productos</h2><p>Transferencia desde proveedor hacia almacén de mina.</p></div><strong class="reception-correlative">{{ $nextCode }}</strong><button type="button" data-close>×</button></div>
    <div class="odoo-statusbar"><span class="complete">Orden aprobada</span><span class="active">Recepción preparada</span><span>Validar entrada</span></div>
    <div class="reception-form">
        <div class="form-section-title"><strong>Documento de origen</strong><span>La orden controla proveedor, destino y cantidades máximas.</span></div>
        <div class="reception-field rec-span-4"><label for="reception-order">Orden de compra aprobada *</label><select id="reception-order" name="purchase_order_id" required><option value="">Seleccionar orden...</option>@foreach($receivableOrders as $order)<option value="{{ $order->id }}">{{ $order->code }} · {{ $order->supplier?->name }}</option>@endforeach</select><small id="order-receipt-status">Selecciona la operación que llegó a mina.</small></div>
        <div class="reception-field rec-span-4"><label>Proveedor</label><input id="reception-supplier-view" value="—" readonly><small>Definido por la cotización ganadora.</small></div>
        <div class="reception-field rec-span-4"><label>Fecha efectiva *</label><input type="date" name="received_at" value="{{ old('received_at',date('Y-m-d')) }}" required><small>Fecha real de ingreso físico.</small></div>
        <div class="reception-field rec-span-4"><label>Sucursal destino</label><input id="reception-branch-view" value="—" readonly></div>
        <div class="reception-field rec-span-4"><label>Almacén destino</label><input id="reception-warehouse-view" value="—" readonly></div>
        <div class="reception-field rec-span-4"><label>Tipo de operación</label><input value="Entrada desde proveedor" readonly></div>

        <div class="form-section-title reception-doc-title"><strong>Documentos de sustento</strong><span>Guía y factura del proveedor; PDF, JPG o PNG, hasta 10 MB.</span></div>
        <div class="reception-document-row compact-document-row"><label>N.º GUÍA<input name="guide_number" value="{{ old('guide_number') }}" placeholder="Número de guía"></label><div class="document-actions"><label class="camera-action">📷 <span>Foto</span><input type="file" name="guide_camera" accept="image/*" capture="environment"></label><label class="attach-action">📎 <span>Adjuntar</span><input type="file" name="guide_file" accept=".pdf,.jpg,.jpeg,.png"></label></div></div>
        <div class="reception-document-row compact-document-row"><label>N.º FACTURA<input name="invoice_number" value="{{ old('invoice_number') }}" placeholder="Número de factura"></label><div class="document-actions"><label class="camera-action">📷 <span>Foto</span><input type="file" name="invoice_camera" accept="image/*" capture="environment"></label><label class="attach-action">📎 <span>Adjuntar</span><input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png"></label></div></div>
        <input type="hidden" name="order_number" id="reception-order-number">

        <div class="requested-products-title"><div><strong>Operaciones detalladas</strong><span class="reception-item-count">0 líneas</span><small>Ingresa únicamente lo recibido físicamente.</small></div></div>
        <div class="reception-items-wrap odoo-lines">
            <div class="reception-item-head"><span>N°</span><span>Producto</span><span>Código</span><span>Solicitado</span><span>Recibido antes</span><span>Pendiente</span><span>Hecho ahora</span><span></span></div>
            <div class="reception-items"><div class="empty-lines">Selecciona una orden para cargar sus productos pendientes.</div></div>
        </div>
        <div class="reception-field reception-notes rec-span-8"><label for="reception-notes">Observaciones de operación</label><textarea id="reception-notes" name="notes" rows="2" maxlength="1000" placeholder="Estado del material, incidencias de transporte o descarga">{{ old('notes') }}</textarea></div>
    </div>
    <div class="modal-foot requirement-foot"><small>Validar crea el movimiento de entrada, actualiza el Kardex y recalcula el saldo de la orden.</small><button type="button" data-close>Cancelar</button><button class="primary" id="validate-reception" disabled>Validar recepción</button></div>
</form></dialog>
@endsection

@push('scripts')
<script>
(() => {
    const orders = @json($orderData);
    const dialog = document.getElementById('new-reception');
    const select = document.getElementById('reception-order');
    const rows = dialog.querySelector('.reception-items');
    const count = dialog.querySelector('.reception-item-count');
    const submit = document.getElementById('validate-reception');
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const refresh = () => {
        const lines = [...rows.querySelectorAll('.reception-item-row')];
        lines.forEach((line,index) => line.querySelector('.row-number').textContent=index+1);
        count.textContent=`${lines.length} ${lines.length===1?'línea':'líneas'}`;
        submit.disabled=lines.length===0;
        lines.forEach(line => line.querySelector('.remove-reception-item').disabled=lines.length===1);
    };
    const renumber=()=>[...rows.querySelectorAll('.reception-item-row')].forEach((line,index)=>line.querySelectorAll('[name]').forEach(input=>input.name=input.name.replace(/items\[\d+\]/,`items[${index}]`)));
    const loadOrder = id => {
        const order=orders.find(item=>String(item.id)===String(id));
        if(!order){
            rows.innerHTML='<div class="empty-lines">Selecciona una orden para cargar sus productos pendientes.</div>';
            ['supplier','branch','warehouse'].forEach(field=>document.getElementById(`reception-${field}-view`).value='—');
            document.getElementById('reception-order-number').value='';
            document.getElementById('order-receipt-status').textContent='Selecciona la operación que llegó a mina.';
            refresh(); return;
        }
        document.getElementById('reception-supplier-view').value=order.supplier;
        document.getElementById('reception-branch-view').value=order.branch;
        document.getElementById('reception-warehouse-view').value=order.warehouse;
        document.getElementById('reception-order-number').value=order.code;
        document.getElementById('order-receipt-status').textContent=`Estado: ${order.status} · ${order.receptions} recepción(es) previa(s)`;
        rows.innerHTML=order.items.map((item,index)=>`<div class="reception-item-row">
            <span class="row-number">${index+1}</span><div class="receipt-product-name"><strong>${escape(item.name)}</strong><small>${escape(item.unit)}</small></div>
            <input value="${escape(item.code||'—')}" readonly><input value="${item.ordered}" readonly><input value="${item.received}" readonly><input value="${item.pending}" readonly>
            <input type="number" min="1" step="1" max="${item.pending}" name="items[${index}][quantity]" value="${item.pending}" required>
            <input type="hidden" name="items[${index}][purchase_order_item_id]" value="${item.id}"><button class="remove-reception-item" type="button" title="No recibir esta línea">×</button>
        </div>`).join('') || '<div class="empty-lines">La orden no contiene productos pendientes.</div>';
        rows.querySelectorAll('.remove-reception-item').forEach(button=>button.addEventListener('click',()=>{button.closest('.reception-item-row').remove();renumber();refresh();}));
        refresh();
    };
    select.addEventListener('change',()=>loadOrder(select.value));
    document.getElementById('open-general-reception').addEventListener('click',()=>{select.value='';loadOrder('');dialog.showModal();});
    document.querySelectorAll('.receive-order').forEach(button=>button.addEventListener('click',()=>{select.value=button.dataset.order;loadOrder(button.dataset.order);dialog.showModal();}));
    refresh();
})();
</script>
@endpush
