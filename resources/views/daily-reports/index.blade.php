@extends('layouts.app')
@section('title','Parte Diario Digital')
@section('content')
<div class="breadcrumb">PARTE DIARIO DIGITAL › Cartillas</div>
<div class="heading"><div><h1>Parte Diario Digital</h1><p>Crea cartillas, asigna usuarios y registra información desde campo.</p></div>@if(auth()->user()->canAccess('users'))<a class="primary" href="{{ route('daily-reports.create') }}">＋ Nueva cartilla</a>@endif</div>
<div class="stats daily-stats">
    <article><span>▤</span><div><small>Cartillas visibles</small><strong>{{ $forms->total() }}</strong></div></article>
    <article><span>✓</span><div><small>Partes registrados</small><strong>{{ $forms->sum('reports_count') }}</strong></div></article>
    <article><span>⌖</span><div><small>Con GPS</small><strong>{{ $forms->where('use_gps',true)->count() }}</strong></div></article>
</div>
<div class="card daily-list-card">
    <form class="toolbar"><label>⌕ <input name="q" value="{{ request('q') }}" placeholder="Buscar cartilla..."></label><button>Buscar</button></form>
    <div class="daily-card-grid">
        @forelse($forms as $form)
        <article class="daily-form-card">
            <div class="daily-card-icon">▤</div><div class="daily-card-main"><div class="daily-card-title"><h3>{{ $form->name }}</h3><span class="badge {{ $form->is_active?'aprobado':'rechazado' }}">{{ $form->is_active?'Activa':'Inactiva' }}</span></div>
            <p>{{ $form->description ?: 'Cartilla digital sin descripción.' }}</p><div class="daily-meta"><span>{{ $form->fields_count }} campos</span><span>{{ $form->reports_count }} registros</span>@if($form->use_gps)<span>⌖ GPS</span>@endif<span>{{ $form->scope ?: 'General' }}</span></div></div>
            <div class="daily-card-actions"><a href="{{ route('daily-reports.fill',$form) }}">Registrar parte</a>@if(auth()->user()->canAccess('users') || $form->created_by===auth()->id())<a href="{{ route('daily-reports.edit',$form) }}">Configurar</a><a href="{{ route('daily-reports.preview',$form) }}">Vista previa</a>@endif</div>
        </article>
        @empty <div class="empty-state">Aún no existen cartillas digitales disponibles.</div>@endforelse
    </div>{{ $forms->links() }}
</div>
@if($recentReports->isNotEmpty())<div class="card recent-daily"><div class="settings-card-head"><div><h2>Registros recientes</h2><p>Últimos partes enviados al sistema.</p></div></div><div class="table-wrap"><table><thead><tr><th>Cartilla</th><th>Usuario</th><th>Fecha y hora</th><th>GPS</th><th>Estado</th></tr></thead><tbody>@foreach($recentReports as $report)<tr><td><strong>{{ $report->form->name }}</strong></td><td>{{ $report->user->name }}</td><td>{{ $report->reported_at->format('d/m/Y H:i') }}</td><td>{{ $report->latitude ? 'Registrado' : 'No requerido' }}</td><td><span class="badge aprobado">{{ $report->status }}</span></td></tr>@endforeach</tbody></table></div></div>@endif
@endsection
