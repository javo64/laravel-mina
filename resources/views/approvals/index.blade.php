@extends('layouts.app')
@section('title','Aprobaciones')
@section('content')
<div class="breadcrumb">ALMACÉN › Aprobaciones</div>
<div class="heading"><div><h1>Aprobaciones por ítem</h1><p>Cada producto se decide de forma independiente y conserva su trazabilidad.</p></div></div>
<nav class="partner-tabs" aria-label="Tipo de documento"><a class="{{ $approvalSection==='requerimientos'?'active':'' }}" href="{{ route('approvals.index') }}">▤ Requerimientos</a><a class="{{ $approvalSection==='cotizaciones'?'active':'' }}" href="{{ route('approvals.index',['seccion'=>'cotizaciones']) }}">▧ Cotizaciones ganadoras</a><a class="{{ $approvalSection==='ordenes'?'active':'' }}" href="{{ route('approvals.index',['seccion'=>'ordenes']) }}">▤ Órdenes de compra</a></nav>

@if($approvalSection==='cotizaciones')
<div class="quotation-approval-grid">@forelse($quotationProcesses as $process)<article class="quotation-approval-card"><header><div><code>{{ $process->requirement->code }}</code><h2>{{ $process->requirement->project }}</h2><p>{{ $process->requirement->responsible }} · versión {{ $process->version }} · enviado por {{ $process->submitter?->name ?? '—' }}</p></div><span class="badge pendiente">Pendiente aprobación</span></header><div class="quotation-files">@foreach($process->quotations as $quote)<a target="_blank" class="{{ $quote->is_winner?'winner':'' }}" href="{{ route('quotations.show',$quote) }}"><span>{{ $quote->is_winner?'★':'▧' }}</span><div><strong>{{ $quote->supplier->name }}</strong><small>{{ $quote->original_name }}{{ $quote->amount!==null?' · '.$quote->currency.' '.number_format($quote->amount,2):'' }}</small></div>@if($quote->is_winner)<b>GANADORA</b>@endif</a>@endforeach</div><footer><form method="post" action="{{ route('approvals.quotations.decide',$process) }}">@csrf<input type="hidden" name="status" value="Rechazada"><textarea name="observation" required placeholder="Observación obligatoria para devolver a Logística"></textarea><button class="reject">× Rechazar y devolver</button></form><form method="post" action="{{ route('approvals.quotations.decide',$process) }}">@csrf<input type="hidden" name="status" value="Aprobada"><button class="approve">✓ Aprobar cotización ganadora</button></form>@if(auth()->user()->isAdministrator())<form method="post" action="{{ route('quotations.destroy',$process) }}" onsubmit="return confirm('¿Eliminar definitivamente este proceso de cotización?')">@csrf @method('DELETE')<button class="danger">Eliminar</button></form>@endif</footer></article>@empty<div class="empty-state"><b>✓</b><p>No hay cotizaciones pendientes de aprobación.</p></div>@endforelse</div>@if(method_exists($quotationProcesses,'links')){{ $quotationProcesses->links() }}@endif
@elseif($approvalSection==='ordenes')
<div class="card approval-items-board">
    <header><div><h2>Órdenes de compra y servicio</h2><p>Doble clic para revisar la orden, sus cotizaciones y el PDF en una sola ventana.</p></div><span class="badge pendiente">{{ $purchaseOrders->total() }} orden(es)</span></header>
    <div class="table-wrap"><table><thead><tr><th>Documento</th><th>Proveedor</th><th>Destino</th><th>Moneda / total</th><th>Creado por</th><th>Cotizaciones</th><th>Estado</th><th>Decisión</th></tr></thead><tbody>
    @forelse($purchaseOrders as $order)
        <tr class="purchase-order-review-row" tabindex="0" data-purchase-order-id="{{ $order->id }}" title="Doble clic para revisar {{ $order->code }}"><td><code>{{ $order->code }}</code><small>{{ $order->created_at->format('d/m/Y H:i') }}</small></td><td><strong>{{ $order->supplier?->name }}</strong><small>{{ $order->items->count() }} ítem(s)</small></td><td>{{ $order->destination_branch }}<small>{{ $order->destination_warehouse }}</small></td><td>{{ $order->currency === 'USD' ? 'US$' : 'S/' }} {{ number_format((float)$order->total,2) }}</td><td>{{ $order->creator?->name ?: '—' }}</td><td><div class="quotation-links">@forelse($order->quotations as $quotation)<a target="_blank" href="{{ route('purchase-orders.quotations.show',$quotation) }}" title="{{ $quotation->original_name }}">▧ {{ \Illuminate\Support\Str::limit($quotation->original_name,20) }}</a>@empty<small>Sin cotización histórica</small>@endforelse</div></td><td><span class="badge {{ strtolower($order->status) }}">{{ $order->status }}</span></td><td><div class="item-decision-actions">@if($order->status!=='Aprobada')<form method="post" action="{{ route('approvals.purchase-orders.decide',$order) }}">@csrf<input type="hidden" name="status" value="Aprobada"><button class="status-action aprobado">✓ Aprobar</button></form>@endif @if($order->status!=='Anulada')<form method="post" action="{{ route('approvals.purchase-orders.decide',$order) }}">@csrf<input type="hidden" name="status" value="Anulada"><button class="status-action anulado">⊘ Anular</button></form>@endif @if(auth()->user()->isAdministrator())<form method="post" action="{{ route('purchase-orders.destroy',$order) }}" onsubmit="return confirm('¿Eliminar definitivamente esta orden?')">@csrf @method('DELETE')<button class="danger">Eliminar</button></form>@endif</div></td></tr>
    @empty<tr><td colspan="8"><div class="empty-state"><b>▧</b><p>No hay órdenes registradas para aprobar.</p></div></td></tr>@endforelse
    </tbody></table></div>{{ $purchaseOrders->links() }}
</div>

@foreach($purchaseOrders as $order)
@php
    $winningQuotation = $order->quotations->first();
@endphp
<dialog class="approval-review-dialog" id="purchase-order-review-{{ $order->id }}">
    <header><div><span>REVISIÓN DE ORDEN</span><h2>{{ $order->code }}</h2><p>{{ $order->document === 'OS' ? 'Orden de servicio' : 'Orden de compra' }} · {{ $order->created_at->format('d/m/Y H:i') }}</p></div><div class="approval-header-status"><small>ESTADO DE APROBACIÓN</small><strong class="badge {{ strtolower($order->status) }}">{{ strtoupper($order->status) }}</strong></div><button type="button" data-close>×</button></header>
    <div class="approval-review-body">
        <section class="approval-detail-pane">
            <article class="approval-detail-card"><h3>Información general</h3><dl><div><dt>Proveedor</dt><dd>{{ $order->supplier?->name ?: '—' }}</dd></div><div><dt>RUC / documento</dt><dd>{{ $order->supplier?->document_number ?: '—' }}</dd></div><div><dt>Destino</dt><dd>{{ $order->destination_branch }} · {{ $order->destination_warehouse }}</dd></div><div><dt>Área</dt><dd>{{ $order->area }}</dd></div><div><dt>Condición de pago</dt><dd>{{ $order->payment_condition }}</dd></div><div><dt>Cuenta bancaria</dt><dd>{{ $order->bankAccount ? ($order->bankAccount->bank?->name ?: $order->bankAccount->bank_name).' · '.$order->bankAccount->account_number : 'No registrada' }}</dd></div></dl></article>
            <article class="approval-detail-card approval-items-card"><h3>Detalle de la orden <b>{{ $order->items->count() }}</b></h3><div class="approval-items-table"><table><thead><tr><th>#</th><th>Producto / servicio</th><th>Cantidad</th><th>Unidad</th><th>P. unitario</th><th>Total</th></tr></thead><tbody>@foreach($order->items as $index=>$line)<tr><td>{{ $index+1 }}</td><td><strong>{{ $line->product_name }}</strong>@if($line->description)<small>{{ $line->description }}</small>@endif</td><td>{{ rtrim(rtrim(number_format((float)$line->quantity,2,'.',''), '0'), '.') }}</td><td>{{ $line->unit }}</td><td>{{ $order->currency==='USD'?'US$':'S/' }} {{ number_format((float)$line->unit_price,2) }}</td><td><strong>{{ $order->currency==='USD'?'US$':'S/' }} {{ number_format((float)$line->total,2) }}</strong></td></tr>@endforeach</tbody></table></div><div class="purchase-review-total"><span>Subtotal <b>{{ number_format((float)$order->subtotal,2) }}</b></span><span>IGV <b>{{ number_format((float)$order->tax,2) }}</b></span><strong>Total {{ $order->currency==='USD'?'US$':'S/' }} {{ number_format((float)$order->total,2) }}</strong></div></article>
            <article class="approval-detail-card"><h3>Cotizaciones adjuntas <b>{{ $order->quotations->count() }}</b></h3><div class="purchase-review-quotations">@forelse($order->quotations as $quotation)<a target="_blank" href="{{ route('purchase-orders.quotations.show',$quotation) }}"><span>▧</span><div><strong>{{ $quotation->original_name }}</strong><small>{{ number_format($quotation->size/1024,1) }} KB</small></div><b>Ver</b></a>@empty<p>Esta orden histórica no tiene cotizaciones adjuntas.</p>@endforelse</div></article>
        </section>
        <section class="approval-pdf-pane purchase-document-viewer"><nav class="purchase-document-tabs" aria-label="Documentos de la orden"><button type="button" class="active" data-document-tab="order">▤ PDF de la orden</button><button type="button" data-document-tab="quotation" {{ $winningQuotation?'':'disabled' }}>★ PDF cotización ganadora</button></nav><div class="purchase-document-panel active" data-document-panel="order"><div class="approval-pdf-toolbar"><div><strong>Orden en PDF</strong><small>Vista previa generada desde la orden</small></div><a target="_blank" href="{{ route('purchase-orders.pdf',[$order,'download'=>1]) }}">⇩ Descargar PDF</a></div><iframe title="PDF de la orden {{ $order->code }}" data-src="{{ route('purchase-orders.pdf',$order) }}#toolbar=1&navpanes=0&view=FitH"></iframe></div><div class="purchase-document-panel" data-document-panel="quotation">@if($winningQuotation)<div class="approval-pdf-toolbar"><div><strong>Cotización ganadora</strong><small>{{ $winningQuotation->original_name }} · {{ $order->supplier?->name }}</small></div><a target="_blank" href="{{ route('purchase-orders.quotations.show',$winningQuotation) }}">↗ Abrir documento</a></div><iframe title="Cotización ganadora de {{ $order->code }}" data-src="{{ route('purchase-orders.quotations.show',$winningQuotation) }}#toolbar=1&navpanes=0&view=FitH"></iframe>@else<div class="purchase-document-empty"><b>▧</b><p>Esta orden histórica no tiene una cotización ganadora vinculada.</p></div>@endif</div></section>
    </div>
    <footer><button type="button" data-close>Cerrar</button><div class="approval-modal-actions">@if($order->status!=='Anulada')<form method="post" action="{{ route('approvals.purchase-orders.decide',$order) }}">@csrf<input type="hidden" name="status" value="Anulada"><button class="reject" type="submit">⊘ Anular orden</button></form>@endif @if($order->status!=='Aprobada')<form method="post" action="{{ route('approvals.purchase-orders.decide',$order) }}">@csrf<input type="hidden" name="status" value="Aprobada"><button class="approve" type="submit">✓ Aprobar orden</button></form>@endif</div></footer>
</dialog>
@endforeach
@else

@php
    $tabs = [
        'Todos'=>['label'=>'Requerimientos','icon'=>'▤','help'=>'Todos los ítems registrados'],
        'Pendiente'=>['label'=>'Pendientes','icon'=>'◷','help'=>'Esperando Visto Bueno'],
        'Visto Bueno'=>['label'=>'Visto Bueno','icon'=>'◉','help'=>'Revisados, pendientes de aprobación final'],
        'Aprobado'=>['label'=>'Aprobados','icon'=>'✓','help'=>'Aprobación final por la cantidad total'],
        'Aprobado parcial'=>['label'=>'Aprobados parcialmente','icon'=>'◐','help'=>'Aprobación por una cantidad menor'],
        'Anulado'=>['label'=>'Anulados','icon'=>'⊘','help'=>'Retirados del proceso'],
    ];
@endphp
<nav class="approval-state-tabs" aria-label="Estados de aprobación">
@foreach($tabs as $status=>$tab)
    <a href="{{ route('approvals.index',['estado'=>$status]) }}" class="{{ $activeStatus===$status?'active':'' }} status-{{ \Illuminate\Support\Str::slug($status) }}">
        <span>{{ $tab['icon'] }}</span><div><strong>{{ $tab['label'] }}</strong><small>{{ $tab['help'] }}</small></div><b>{{ $status==='Todos' ? $totalRequirements : ($counts[$status] ?? 0) }}</b>
    </a>
@endforeach
</nav>

<div class="card approval-items-board">
    <header><div><h2>{{ $tabs[$activeStatus]['label'] }}</h2><p>{{ $tabs[$activeStatus]['help'] }} · doble clic para revisar el requerimiento completo</p></div><span class="badge {{ \Illuminate\Support\Str::slug($activeStatus) }}">{{ $activeStatus==='Todos' ? $requirements->total().' requerimiento(s)' : $items->total().' ítem(s)' }}</span></header>
    @if($activeStatus==='Todos')
    <div class="table-wrap"><table><thead><tr><th>Requerimiento</th><th>Fecha</th><th>Solicitante</th><th>Proyecto / área</th><th>Ítems</th><th>Pendientes</th><th>Visto Bueno</th><th>Aprobados</th><th>Aprob. parcial</th><th>Anulados</th><th>Estado general</th></tr></thead><tbody>
    @forelse($requirements as $requirement)
        @php($grouped=$requirement->items->countBy('approval_status'))
        <tr class="approval-review-row" tabindex="0" data-requirement-id="{{ $requirement->id }}" title="Doble clic para revisar {{ $requirement->code }}"><td><code>{{ $requirement->code }}</code></td><td>{{ $requirement->requested_at->format('d/m/Y') }}</td><td>{{ $requirement->responsible }}</td><td><strong>{{ $requirement->project }}</strong><small>{{ $requirement->area ?: 'Sin área' }}</small></td><td>{{ $requirement->items->count() }}</td><td>{{ $grouped['Pendiente'] ?? 0 }}</td><td>{{ $grouped['Visto Bueno'] ?? 0 }}</td><td>{{ $grouped['Aprobado'] ?? 0 }}</td><td>{{ $grouped['Aprobado parcial'] ?? 0 }}</td><td>{{ $grouped['Anulado'] ?? 0 }}</td><td><span class="badge {{ \Illuminate\Support\Str::slug($requirement->status) }}">{{ $requirement->status }}</span></td></tr>
    @empty
        <tr><td colspan="11"><div class="empty-state"><b>▤</b><p>No hay requerimientos registrados.</p></div></td></tr>
    @endforelse
    </tbody></table></div>
    {{ $requirements->links() }}
    @else
    <div class="table-wrap"><table><thead><tr><th>Requerimiento</th><th>Producto / servicio</th><th>Cantidad requerida</th><th>Unidad</th><th>Cantidad solicitada</th><th>Solicitante</th><th>Proyecto / área</th><th>Estado</th><th>Decidido por</th></tr></thead><tbody>
    @forelse($items as $detail)
        @php($requirement=$detail->requirement)
        <tr class="approval-review-row" tabindex="0" data-requirement-id="{{ $requirement->id }}" title="Doble clic para revisar {{ $requirement->code }}">
            <td><code>{{ $requirement->code }}</code><small>{{ $requirement->requested_at->format('d/m/Y') }}</small></td>
            <td><strong>{{ $detail->product_name }}</strong>@if($detail->description)<small>{{ $detail->description }}</small>@endif</td>
            <td><strong>{{ rtrim(rtrim(number_format((float)$detail->quantity,2,'.',''), '0'), '.') }}</strong></td>
            <td>{{ $detail->unit }}</td>
            <td><strong>{{ $detail->approved_quantity !== null ? rtrim(rtrim(number_format((float)$detail->approved_quantity,2,'.',''), '0'), '.') : '—' }}</strong></td>
            <td>{{ $requirement->responsible }}</td>
            <td><strong>{{ $requirement->project }}</strong><small>{{ $requirement->area ?: 'Sin área' }}</small></td>
            <td><div class="approval-status-with-image"><span class="badge {{ \Illuminate\Support\Str::slug($detail->approval_status) }}">{{ $detail->approval_status }}</span>@if($detail->image_path)<button type="button" class="view-item-image" data-image-url="{{ route('approvals.items.image',$detail) }}" data-image-title="{{ $detail->product_name }}" title="Ver imagen del producto">▧ Ver imagen</button>@endif</div></td>
            <td>@if($detail->decision_at)<strong>{{ $detail->decisionMaker?->name ?: 'Usuario retirado' }}</strong><small>{{ $detail->decision_at->format('d/m/Y H:i') }}</small>@else<small>Sin decisión</small>@endif</td>
        </tr>
    @empty
        <tr><td colspan="9"><div class="empty-state"><b>{{ $tabs[$activeStatus]['icon'] }}</b><p>No hay ítems en el bloque {{ strtolower($tabs[$activeStatus]['label']) }}.</p></div></td></tr>
    @endforelse
    </tbody></table></div>
    {{ $items->links() }}
    @endif
</div>

@foreach($requirements as $requirement)
<dialog class="approval-review-dialog" id="approval-review-{{ $requirement->id }}">
    <header><div><span>REVISIÓN DE REQUERIMIENTO</span><h2>{{ $requirement->code }}</h2><p>Detalle completo y representación PDF</p></div><div class="approval-header-status"><small>ESTADO DE APROBACIÓN</small><strong class="badge {{ \Illuminate\Support\Str::slug($requirement->status) }}">{{ strtoupper($requirement->status) }}</strong></div><button type="button" data-close>×</button></header>
    <div class="approval-review-body">
        <section class="approval-detail-pane">
            <article class="approval-detail-card"><h3>Información general</h3><dl><div><dt>Fecha</dt><dd>{{ $requirement->requested_at->format('d/m/Y') }}</dd></div><div><dt>Responsable</dt><dd>{{ $requirement->responsible }}</dd></div><div><dt>Proyecto</dt><dd>{{ $requirement->project }}</dd></div><div><dt>Área solicitante</dt><dd>{{ $requirement->area ?: 'No indicada' }}</dd></div><div><dt>Prioridad</dt><dd>{{ $requirement->priority }}</dd></div><div><dt>Estado general</dt><dd><span class="badge {{ strtolower($requirement->status) }}">{{ $requirement->status }}</span></dd></div></dl></article>
            <article class="approval-detail-card approval-items-card">
                <h3>Decisión por ítems <b>{{ $requirement->items->count() }}</b></h3>
                <div class="approval-items-table"><table><thead><tr><th>#</th><th>Producto</th><th>Cantidad requerida</th><th>Unidad</th><th>Cantidad solicitada</th><th>Estado</th><th>Decidido por / aprobación</th></tr></thead><tbody>
                @foreach($requirement->items as $index=>$line)
                    <tr class="approval-quantity-row">
                        <td>{{ $index+1 }}</td>
                        <td><strong>{{ $line->product_name }}</strong>@if($line->description)<small>{{ $line->description }}</small>@endif</td>
                        <td><strong>{{ rtrim(rtrim(number_format((float)$line->quantity,2,'.',''), '0'), '.') }}</strong></td>
                        <td>{{ $line->unit }}</td>
                        <td><input class="approved-quantity-input" type="number" min="0.01" max="{{ (float)$line->quantity }}" step="0.01" value="{{ (float)($line->approved_quantity ?? $line->quantity) }}" aria-label="Cantidad solicitada para {{ $line->product_name }}"></td>
                        <td><div class="approval-status-with-image"><span class="badge {{ \Illuminate\Support\Str::slug($line->approval_status) }}">{{ $line->approval_status }}</span>@if($line->image_path)<button type="button" class="view-item-image" data-image-url="{{ route('approvals.items.image',$line) }}" data-image-title="{{ $line->product_name }}">▧ Ver imagen</button>@endif</div></td>
                        <td class="item-review-decision">
                            <div>
                                @if($line->reviewed_at)<strong>Visto bueno: {{ $line->reviewer?->name ?: 'Usuario retirado' }}</strong><small>{{ $line->reviewed_at->format('d/m/Y H:i') }}</small>@endif
                                @if($line->decision_at)<strong>Aprobación: {{ $line->decisionMaker?->name ?: 'Usuario retirado' }}</strong><small>{{ $line->decision_at->format('d/m/Y H:i') }}</small>@endif
                                @if(! $line->reviewed_at && ! $line->decision_at)<small>Sin decisión</small>@endif
                            </div>
                            <div class="item-decision-actions">
                                @if($canReview && $line->approval_status === 'Pendiente')
                                    <form method="post" action="{{ route('approvals.items.decide', $line) }}">@csrf<input type="hidden" name="status" value="Visto Bueno"><button class="status-action visto-bueno" title="Dar Visto Bueno">◉ <span>Visto Bueno</span></button></form>
                                @endif
                                @if($canApprove && $line->approval_status === 'Visto Bueno')
                                    <form method="post" action="{{ route('approvals.items.decide', $line) }}">@csrf<input type="hidden" name="status" value="Aprobado"><input class="decision-quantity" type="hidden" name="approved_quantity"><button class="status-action aprobado" title="Aprobar toda la cantidad">✓ <span>Aprobar</span></button></form>
                                    <form method="post" class="partial-approval-form" action="{{ route('approvals.items.decide', $line) }}">@csrf<input type="hidden" name="status" value="Aprobado parcial"><input class="decision-quantity" type="hidden" name="approved_quantity"><button class="status-action aprobado-parcial" title="Aprobar una cantidad menor">◐ <span>Aprobar parcial</span></button></form>
                                @endif
                                @if($canApprove && !in_array($line->approval_status, ['Aprobado', 'Anulado'], true))
                                    <form method="post" action="{{ route('approvals.items.decide', $line) }}">@csrf<input type="hidden" name="status" value="Anulado"><button class="status-action anulado" title="Anular el ítem">⊘ <span>Anular</span></button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
            </article>
            <article class="approval-detail-card approval-trace"><h3>Trazabilidad</h3><p>El flujo es: Pendiente → Visto Bueno → Aprobación. Los usuarios con permiso de Visto Bueno revisan primero; solo quienes tienen aprobación final pueden autorizar los ítems para una orden de compra.</p></article>
        </section>
        <section class="approval-pdf-pane"><div class="approval-pdf-toolbar"><div><strong>Documento PDF</strong><small>Vista generada desde el requerimiento</small></div><a href="{{ route('approvals.pdf',[$requirement,'download'=>1]) }}">⇩ Descargar PDF</a></div><iframe title="PDF del requerimiento {{ $requirement->code }}" data-src="{{ route('approvals.pdf',$requirement) }}#toolbar=1&navpanes=0&view=FitH"></iframe></section>
    </div>
    <footer><button type="button" data-close>Cerrar</button><div class="approval-modal-actions">
        @if($canReview && $requirement->status === 'Pendiente')
            <form method="post" action="{{ route('approvals.decide', $requirement) }}">@csrf<input type="hidden" name="status" value="Visto Bueno"><button class="status-action visto-bueno" type="submit">◉ Visto Bueno total</button></form>
        @endif
        @if($canApprove && $requirement->status === 'Visto Bueno')
            <form method="post" action="{{ route('approvals.decide', $requirement) }}">@csrf<input type="hidden" name="status" value="Aprobado"><button class="approve" type="submit">✓ Aprobar total</button></form>
        @endif
        @if($canApprove && $requirement->status !== 'Aprobación')
            <form method="post" action="{{ route('approvals.decide', $requirement) }}">@csrf<input type="hidden" name="status" value="Anulado"><button class="reject" type="submit">⊘ Anular total</button></form>
        @endif
    </div></footer>
</dialog>
@endforeach
<dialog class="item-image-dialog" id="item-image-dialog"><header><div><span>IMAGEN DEL PRODUCTO</span><h2 id="item-image-title">Producto solicitado</h2></div><button type="button" data-close>×</button></header><div class="item-image-stage"><img id="item-image-preview" alt="Imagen adjunta del producto solicitado"></div><footer><button type="button" data-close>Cerrar</button></footer></dialog>
@endif
@endsection
@push('scripts')
<script>
document.querySelectorAll('.view-item-image').forEach(button => button.addEventListener('click', event => {
    event.stopPropagation();
    const dialog = document.getElementById('item-image-dialog');
    document.getElementById('item-image-title').textContent = button.dataset.imageTitle;
    document.getElementById('item-image-preview').src = button.dataset.imageUrl;
    dialog.showModal();
}));
document.querySelectorAll('.approval-quantity-row').forEach(row => {
    const quantity = row.querySelector('.approved-quantity-input');
    const partialForm = row.querySelector('.partial-approval-form');
    if (!quantity) return;
    const synchronize = () => {
        const value = Number(quantity.value);
        const required = Number(quantity.max);
        row.querySelectorAll('.decision-quantity').forEach(input => input.value = quantity.value);
        if (partialForm) partialForm.hidden = !(value > 0 && value < required);
        quantity.setCustomValidity(value > required ? 'La cantidad solicitada no puede superar la cantidad requerida.' : '');
    };
    quantity.addEventListener('input', synchronize);
    row.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
        synchronize();
        if (!quantity.reportValidity() || (partialForm && form === partialForm && partialForm.hidden)) event.preventDefault();
    }));
    synchronize();
});
document.querySelectorAll('.purchase-order-review-row').forEach(row => {
    const openReview = () => {
        const dialog = document.getElementById('purchase-order-review-' + row.dataset.purchaseOrderId);
        const frame = dialog?.querySelector('iframe[data-src]');
        if (frame && !frame.src) frame.src = frame.dataset.src;
        dialog?.showModal();
    };
    row.addEventListener('dblclick', event => { if (!event.target.closest('button,a,form')) openReview(); });
    row.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.target.closest('button,a,form')) openReview(); });
});
document.querySelectorAll('.purchase-document-viewer').forEach(viewer=>{
    const buttons=[...viewer.querySelectorAll('[data-document-tab]')],panels=[...viewer.querySelectorAll('[data-document-panel]')];
    buttons.forEach(button=>button.addEventListener('click',()=>{
        if(button.disabled)return;
        buttons.forEach(item=>item.classList.toggle('active',item===button));
        panels.forEach(panel=>{const active=panel.dataset.documentPanel===button.dataset.documentTab;panel.classList.toggle('active',active);if(active){const frame=panel.querySelector('iframe[data-src]');if(frame&&!frame.src)frame.src=frame.dataset.src;}});
    }));
});
document.querySelectorAll('.approval-review-row').forEach(row=>{
    const openReview=()=>{const dialog=document.getElementById('approval-review-'+row.dataset.requirementId);const frame=dialog?.querySelector('iframe[data-src]');if(frame&&!frame.src)frame.src=frame.dataset.src;dialog?.showModal()};
    row.addEventListener('dblclick',event=>{if(!event.target.closest('button,a,form'))openReview()});
    row.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.target.closest('button,a'))openReview()});
});
</script>
@endpush
