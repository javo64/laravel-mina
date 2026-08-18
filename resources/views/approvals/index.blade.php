@extends('layouts.app')
@section('title','Aprobaciones')
@section('content')
<div class="breadcrumb">ALMACÉN › Aprobaciones</div>
<div class="heading"><div><h1>Aprobaciones</h1><p>Haz doble clic en un requerimiento para revisar su detalle y documento PDF.</p></div></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Código</th><th>Fecha</th><th>Responsable</th><th>Proyecto</th><th>Ítems</th><th>Estado</th><th>Fecha de aprobación</th><th>Decisión</th>@if(auth()->user()->isAdministrator())<th></th>@endif</tr></thead><tbody>
@foreach($requirements as $item)
<tr class="approval-review-row" tabindex="0" data-requirement-id="{{ $item->id }}" title="Doble clic para revisar el requerimiento completo">
    <td><code>{{ $item->code }}</code></td><td>{{ $item->requested_at->format('d/m/Y') }}</td><td>{{ $item->responsible }}</td><td><strong>{{ $item->project }}</strong></td><td>{{ $item->items->count() }}</td><td><span class="badge {{ strtolower($item->status) }}">{{ $item->status }}</span></td><td>{{ $item->decision_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</td>
    <td><div class="decision"><form method="post" action="{{ route('approvals.decide',$item) }}">@csrf<input type="hidden" name="status" value="Aprobado"><button class="approve" {{ $item->status==='Aprobado'?'disabled':'' }}>✓ Aprobar</button></form><form method="post" action="{{ route('approvals.decide',$item) }}">@csrf<input type="hidden" name="status" value="Rechazado"><button class="reject" {{ $item->status==='Rechazado'?'disabled':'' }}>× Rechazar</button></form></div></td>
    @if(auth()->user()->isAdministrator())<td>@if($item->status!=='Pendiente')<form method="post" action="{{ route('approvals.destroy',$item) }}" onsubmit="return confirm('¿Eliminar esta aprobación y devolver el requerimiento a Pendiente?')">@csrf @method('DELETE')<button class="danger">Eliminar aprobación</button></form>@endif</td>@endif
</tr>
@endforeach
</tbody></table></div>{{ $requirements->links() }}</div>

@foreach($requirements as $item)
<dialog class="approval-review-dialog" id="approval-review-{{ $item->id }}">
    <header><div><span>REVISIÓN DE REQUERIMIENTO</span><h2>{{ $item->code }}</h2><p>Detalle completo y representación PDF</p></div><button type="button" data-close>×</button></header>
    <div class="approval-review-body">
        <section class="approval-detail-pane">
            <article class="approval-detail-card"><h3>Información general</h3><dl><div><dt>Fecha</dt><dd>{{ $item->requested_at->format('d/m/Y') }}</dd></div><div><dt>Responsable</dt><dd>{{ $item->responsible }}</dd></div><div><dt>Proyecto</dt><dd>{{ $item->project }}</dd></div><div><dt>Área solicitante</dt><dd>{{ $item->area ?: 'No indicada' }}</dd></div><div><dt>Prioridad</dt><dd>{{ $item->priority }}</dd></div><div><dt>Estado</dt><dd><span class="badge {{ strtolower($item->status) }}">{{ $item->status }}</span></dd></div>@if($item->decision_at)<div><dt>Decisión</dt><dd>{{ $item->decision_at->format('d/m/Y H:i') }} · {{ $item->decisionMaker?->name ?: 'Usuario retirado' }}</dd></div>@endif</dl></article>
            <article class="approval-detail-card approval-items-card"><h3>Ítems solicitados <b>{{ $item->items->count() }}</b></h3><div class="approval-items-table"><table><thead><tr><th>#</th><th>Producto</th><th>Cantidad</th><th>Unidad</th><th>Prioridad</th></tr></thead><tbody>@foreach($item->items as $index=>$detail)<tr><td>{{ $index+1 }}</td><td><strong>{{ $detail->product_name }}</strong>@if($detail->description)<small>{{ $detail->description }}</small>@endif</td><td>{{ rtrim(rtrim(number_format((float)$detail->quantity,2,'.',''), '0'), '.') }}</td><td>{{ $detail->unit }}</td><td>{{ $detail->priority }}</td></tr>@endforeach</tbody></table></div></article>
            <article class="approval-detail-card approval-trace"><h3>Trazabilidad</h3><p>@if($item->decision_at){{ $item->status }} el {{ $item->decision_at->format('d/m/Y H:i') }} por {{ $item->decisionMaker?->name ?: 'Usuario retirado' }}.@else Pendiente de revisión y decisión por un usuario autorizado.@endif</p></article>
        </section>
        <section class="approval-pdf-pane"><div class="approval-pdf-toolbar"><div><strong>Documento PDF</strong><small>Vista generada desde el requerimiento</small></div><a href="{{ route('approvals.pdf',[$item,'download'=>1]) }}">⇩ Descargar PDF</a></div><iframe title="PDF del requerimiento {{ $item->code }}" data-src="{{ route('approvals.pdf',$item) }}#toolbar=1&navpanes=0&view=FitH"></iframe></section>
    </div>
    <footer><button type="button" data-close>Cerrar</button><div class="approval-modal-actions"><form method="post" action="{{ route('approvals.decide',$item) }}">@csrf<input type="hidden" name="status" value="Rechazado"><button class="reject" {{ $item->status==='Rechazado'?'disabled':'' }}>× Rechazar</button></form><form method="post" action="{{ route('approvals.decide',$item) }}">@csrf<input type="hidden" name="status" value="Aprobado"><button class="approve" {{ $item->status==='Aprobado'?'disabled':'' }}>✓ Aprobar</button></form></div></footer>
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
