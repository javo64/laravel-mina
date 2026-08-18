@extends('layouts.mobile')
@section('title','Mis cartillas')
@section('content')
<section class="mobile-welcome"><span>{{ now()->format('d/m/Y') }}</span><h1>Hola, {{ strtok(auth()->user()->name, ' ') }}</h1><p>Selecciona una cartilla para registrar información.</p></section>
<section class="mobile-cartillas"><h2>Mis cartillas <b>{{ $forms->count() }}</b></h2>@forelse($forms as $form)<a href="{{ route('mobile.daily-reports.fill',$form) }}"><span class="mobile-cartilla-icon">▤</span><div><strong>{{ $form->name }}</strong><small>{{ $form->scope ?: 'Operación general' }} · {{ $form->fields_count }} campos</small>@if($form->use_gps)<em>⌖ Requiere GPS</em>@endif</div><b>›</b></a>@empty<div class="mobile-empty"><b>▤</b><p>No tienes cartillas asignadas.</p><small>Solicita al administrador que te asigne una cartilla activa.</small></div>@endforelse</section>
@if($myReports->isNotEmpty())<section class="mobile-recent"><h2>Enviados recientemente</h2>@foreach($myReports as $report)<article><span>✓</span><div><strong>{{ $report->form->name }}</strong><small>{{ $report->reported_at->format('d/m/Y H:i') }}</small></div></article>@endforeach</section>@endif
@endsection
