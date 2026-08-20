@extends('layouts.app')
@section('title','Aprobaciones')
@section('content')
<div class="breadcrumb">ALMACÉN › Aprobaciones</div>
<div class="heading"><div><h1>Aprobaciones por ítem</h1><p>Cada producto se decide de forma independiente y conserva su trazabilidad.</p></div></div>

@php
    $tabs = [
        'Todos'=>['label'=>'Requerimientos','icon'=>'▤','help'=>'Todos los ítems registrados'],
        'Pendiente'=>['label'=>'Pendientes','icon'=>'◷','help'=>'Esperando aprobación'],
        'Aprobado'=>['label'=>'Aprobados','icon'=>'✓','help'=>'Aptos para orden de compra'],
        'Anulado'=>['label'=>'Anulados','icon'=>'⊘','help'=>'Retirados del proceso'],
    ];
@endphp
<nav class="approval-state-tabs" aria-label="Estados de aprobación">
@foreach($tabs as $status=>$tab)
    <a href="{{ route('approvals.index',['estado'=>$status]) }}" class="{{ $activeStatus===$status?'active':'' }} status-{{ strtolower($status) }}">
        <span>{{ $tab['icon'] }}</span><div><strong>{{ $tab['label'] }}</strong><small>{{ $tab['help'] }}</small></div><b>{{ $status==='Todos' ? $items->total() : ($counts[$status] ?? 0) }}</b>
    </a>
@endforeach
</nav>

<div class="card approval-items-board">
    <header><div><h2>{{ $tabs[$activeStatus]['label'] }}</h2><p>{{ $tabs[$activeStatus]['help'] }} · doble clic para revisar el requerimiento completo</p></div><span class="badge {{ strtolower($activeStatus) }}">{{ $items->total() }} ítem(s)</span></header>
    <div class="table-wrap"><table><thead><tr><th>Requerimiento</th><th>Producto / servicio</th><th>Cantidad</th><th>Solicitante</th><th>Proyecto / área</th><th>Estado</th><th>Decidido por</th></tr></thead><tbody>
    @forelse($items as $detail)
        @php($requirement=$detail->requirement)
        <tr class="approval-review-row" tabindex="0" data-requirement-id="{{ $requirement->id }}" title="Doble clic para revisar {{ $requirement->code }}">
            <td><code>{{ $requirement->code }}</code><small>{{ $requirement->requested_at->format('d/m/Y') }}</small></td>
            <td><strong>{{ $detail->product_name }}</strong>@if($detail->description)<small>{{ $detail->description }}</small>@endif</td>
            <td><strong>{{ rtrim(rtrim(number_format((float)$detail->quantity,2,'.',''), '0'), '.') }}</strong> {{ $detail->unit }}</td>
            <td>{{ $requirement->responsible }}</td>
            <td><strong>{{ $requirement->project }}</strong><small>{{ $requirement->area ?: 'Sin área' }}</small></td>
            <td><span class="badge {{ strtolower($detail->approval_status) }}">{{ $detail->approval_status }}</span></td>
            <td>@if($detail->decision_at)<strong>{{ $detail->decisionMaker?->name ?: 'Usuario retirado' }}</strong><small>{{ $detail->decision_at->format('d/m/Y H:i') }}</small>@else<small>Sin decisión</small>@endif</td>
        </tr>
    @empty
        <tr><td colspan="7"><div class="empty-state"><b>{{ $tabs[$activeStatus]['icon'] }}</b><p>No hay ítems en el bloque {{ strtolower($tabs[$activeStatus]['label']) }}.</p></div></td></tr>
    @endforelse
    </tbody></table></div>
    {{ $items->links() }}
</div>

@foreach($requirements as $requirement)
<dialog class="approval-review-dialog" id="approval-review-{{ $requirement->id }}">
    <header><div><span>REVISIÓN DE REQUERIMIENTO</span><h2>{{ $requirement->code }}</h2><p>Detalle completo y representación PDF</p></div><button type="button" data-close>×</button></header>
    <div class="approval-review-body">
        <section class="approval-detail-pane">
            <article class="approval-detail-card"><h3>Información general</h3><dl><div><dt>Fecha</dt><dd>{{ $requirement->requested_at->format('d/m/Y') }}</dd></div><div><dt>Responsable</dt><dd>{{ $requirement->responsible }}</dd></div><div><dt>Proyecto</dt><dd>{{ $requirement->project }}</dd></div><div><dt>Área solicitante</dt><dd>{{ $requirement->area ?: 'No indicada' }}</dd></div><div><dt>Prioridad</dt><dd>{{ $requirement->priority }}</dd></div><div><dt>Estado general</dt><dd><span class="badge {{ strtolower($requirement->status) }}">{{ $requirement->status }}</span></dd></div></dl></article>
            <article class="approval-detail-card approval-items-card"><h3>Decisión por ítems <b>{{ $requirement->items->count() }}</b></h3><div class="approval-items-table"><table><thead><tr><th>#</th><th>Producto</th><th>Cantidad</th><th>Estado</th><th>Decidido por / aprobación</th></tr></thead><tbody>@foreach($requirement->items as $index=>$line)<tr><td>{{ $index+1 }}</td><td><strong>{{ $line->product_name }}</strong>@if($line->description)<small>{{ $line->description }}</small>@endif</td><td>{{ rtrim(rtrim(number_format((float)$line->quantity,2,'.',''), '0'), '.') }} {{ $line->unit }}</td><td><span class="badge {{ strtolower($line->approval_status) }}">{{ $line->approval_status }}</span></td><td class="item-review-decision"><div>{{ $line->decisionMaker?->name ?: '—' }}@if($line->decision_at)<small>{{ $line->decision_at->format('d/m/Y H:i') }}</small>@endif</div><div class="item-decision-actions">@foreach(['Aprobado'=>'✓','Anulado'=>'⊘'] as $status=>$icon)@if($status!==$line->approval_status)<form method="post" action="{{ route('approvals.items.decide',$line) }}">@csrf<input type="hidden" name="status" value="{{ $status }}"><button class="status-action {{ strtolower($status) }}" title="Marcar como {{ $status }}">{{ $icon }} <span>{{ $status }}</span></button></form>@endif @endforeach</div></td></tr>@endforeach</tbody></table></div></article>
            <article class="approval-detail-card approval-trace"><h3>Trazabilidad</h3><p>El estado general se calcula automáticamente a partir de las decisiones individuales. Los ítems aprobados quedan disponibles para integrar una orden de compra.</p></article>
        </section>
        <section class="approval-pdf-pane"><div class="approval-pdf-toolbar"><div><strong>Documento PDF</strong><small>Vista generada desde el requerimiento</small></div><a href="{{ route('approvals.pdf',[$requirement,'download'=>1]) }}">⇩ Descargar PDF</a></div><iframe title="PDF del requerimiento {{ $requirement->code }}" data-src="{{ route('approvals.pdf',$requirement) }}#toolbar=1&navpanes=0&view=FitH"></iframe></section>
    </div>
    <footer><button type="button" data-close>Cerrar</button><div class="approval-modal-actions"><form method="post" action="{{ route('approvals.decide',$requirement) }}">@csrf<input type="hidden" name="status" value="Aprobado"><button class="approve" type="submit">✓ Aprobar total</button></form></div></footer>
</dialog>
@endforeach
@endsection
@push('scripts')
<script>
document.querySelectorAll('.approval-review-row').forEach(row=>{
    const openReview=()=>{const dialog=document.getElementById('approval-review-'+row.dataset.requirementId);const frame=dialog?.querySelector('iframe[data-src]');if(frame&&!frame.src)frame.src=frame.dataset.src;dialog?.showModal()};
    row.addEventListener('dblclick',event=>{if(!event.target.closest('button,a,form'))openReview()});
    row.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.target.closest('button,a'))openReview()});
});
</script>
@endpush
