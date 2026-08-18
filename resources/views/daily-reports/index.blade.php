@extends('layouts.app')
@section('title','Creación de Cartillas')
@section('content')
<div class="breadcrumb">PARTE DIARIO DIGITAL › Creación de Cartillas</div>
<div class="heading"><div><h1>Creación de Cartillas</h1><p>Diseña los campos, configura las opciones y asigna los usuarios responsables.</p></div>@if(auth()->user()->canAccess('users'))<a class="primary" href="{{ route('daily-reports.create') }}">＋ Nueva cartilla</a>@endif</div>
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
            <div class="daily-card-actions">@if(auth()->user()->canAccess('users') || $form->created_by===auth()->id())<a href="{{ route('daily-reports.edit',$form) }}">Configurar</a><a href="{{ route('daily-reports.preview',$form) }}">Vista previa</a>@endif</div>
        </article>
        @empty <div class="empty-state">Aún no existen cartillas digitales disponibles.</div>@endforelse
    </div>{{ $forms->links() }}
</div>
@endsection
