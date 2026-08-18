@extends('layouts.app')
@section('title','Registro de Cartillas')
@section('content')
<div class="breadcrumb">PARTE DIARIO DIGITAL › Registro de Cartillas</div>
<div class="heading records-heading"><div><h1>Registro de Cartillas</h1><p>Central de registros enviados por los usuarios desde campo.</p></div><div class="new-capture"><select id="new-capture-form"><option value="">Seleccionar cartilla para registrar...</option>@foreach($availableForms as $form)<option value="{{ route('daily-reports.fill',$form) }}">{{ $form->name }}</option>@endforeach</select><button class="primary" type="button" id="new-capture-button">＋ Nuevo registro</button></div></div>

<form class="records-filters card" method="get">
    <label><span>▤ Cartilla registrada</span><select name="form_id"><option value="">Todas las cartillas</option>@foreach($registeredForms as $form)<option value="{{ $form->id }}" @selected((string)request('form_id')===(string)$form->id)>{{ $form->name }}</option>@endforeach</select></label>
    <label><span>▣ Fecha</span><input type="date" name="date" value="{{ request('date') }}"></label>
    <label><span>♟ Usuario que registró</span><select name="user_id"><option value="">Todos los usuarios</option>@foreach($users as $recordUser)<option value="{{ $recordUser->id }}" @selected((string)request('user_id')===(string)$recordUser->id)>{{ $recordUser->name }}</option>@endforeach</select></label>
    <button class="primary">Aplicar filtros</button><a href="{{ route('daily-reports.records') }}">Limpiar</a>
</form>

<div class="records-summary">
    <span><small>Registros</small><strong>{{ $totalReports }}</strong></span><span><small>Con GPS</small><strong>{{ $gpsReports }}</strong></span><span><small>Usuarios</small><strong>{{ $reportingUsers }}</strong></span>
</div>
@if($totalReports>$gpsReports)<div class="records-gps-warning"><b>⌖ {{ $totalReports-$gpsReports }} registro(s) sin ubicación</b><span>Estos registros fueron guardados sin coordenadas y no pueden mostrarse en el mapa. Los nuevos registros con GPS activado sí formarán parte de la traza.</span></div>@endif

<div class="records-central card">
    <section class="records-table-panel">
        <div class="records-panel-title"><div><h2>Registros encontrados</h2><p>Doble clic en una fila para ver todo el contenido registrado.</p></div><div class="records-panel-actions"><a href="{{ route('daily-reports.export',request()->only(['form_id','date','user_id'])) }}">⇩ Exportar Excel</a><b>{{ $reports->total() }}</b></div></div>
        <div class="records-table-scroll"><table><thead><tr><th>Fecha y hora</th><th>Cartilla</th><th>Usuario</th><th>Datos registrados</th><th>GPS</th></tr></thead><tbody>
        @forelse($reports as $report)<tr class="record-row" tabindex="0" title="Doble clic para ver el registro completo" data-report-id="{{ $report->id }}" data-lat="{{ $report->latitude }}" data-lng="{{ $report->longitude }}">
            <td><strong>{{ $report->reported_at->format('d/m/Y') }}</strong><small>{{ $report->reported_at->format('H:i') }}</small></td>
            <td><strong>{{ $report->form->name }}</strong><small>{{ $report->form->scope ?: 'General' }}</small></td>
            <td>{{ $report->user->name }}</td>
            <td><div class="record-values">@foreach(collect($report->responses)->take(3) as $key=>$value)@php($field=$report->form->fields->firstWhere('field_key',$key))<span><b>{{ $field?->name ?? $key }}:</b> {{ is_array($value)?implode(', ',$value):($value?:'—') }}</span>@endforeach</div></td>
            <td>@if($report->latitude)<button type="button" class="locate-report" data-id="{{ $report->id }}">⌖ Ver punto</button><a target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $report->latitude }},{{ $report->longitude }}">Google Maps ↗</a>@else<span class="no-gps">Sin GPS</span>@endif</td>
        </tr>@empty<tr><td colspan="5"><div class="empty-state">No existen registros para los filtros seleccionados.</div></td></tr>@endforelse
        </tbody></table></div>
        <div class="records-pagination">{{ $reports->links() }}</div>
    </section>
    <section class="records-map-panel"><div class="map-title"><div><h2>Mapa de puntos registrados</h2><p>{{ $gpsReports }} ubicaciones con coordenadas GPS</p></div><span>⌖</span></div><div id="records-map"></div><div id="map-empty" @if($mapPoints->isNotEmpty()) hidden @endif><b>⌖</b><span>No hay puntos GPS para mostrar.</span></div></section>
</div>

@foreach($reports as $report)
<dialog class="record-detail-dialog" id="record-detail-{{ $report->id }}">
    <header><div><span>REGISTRO #{{ $report->id }}</span><h2>{{ $report->form->name }}</h2><p>Información completa enviada desde la cartilla.</p></div><button type="button" data-close>×</button></header>
    <div class="record-detail-summary"><span><small>Fecha y hora</small><strong>{{ $report->reported_at->format('d/m/Y H:i:s') }}</strong></span><span><small>Usuario</small><strong>{{ $report->user->name }}</strong></span><span><small>Estado</small><strong>{{ $report->status }}</strong></span><span><small>Ámbito</small><strong>{{ $report->form->scope ?: 'General' }}</strong></span></div>
    <section class="record-detail-content"><h3>Contenido registrado</h3><div class="record-detail-grid">
        @forelse($report->responses as $key=>$value)@php($field=$report->form->fields->firstWhere('field_key',$key))
        <article><small>{{ $field?->section ?: 'Datos de la cartilla' }}</small><strong>{{ $field?->name ?? str_replace('_',' ',mb_strtoupper($key)) }}</strong>
            @if($field?->type==='photo' && $value)<a class="record-media" target="_blank" href="{{ asset('storage/'.$value) }}"><img src="{{ asset('storage/'.$value) }}" alt="{{ $field->name }}"><span>Abrir fotografía ↗</span></a>
            @elseif($field?->type==='signature' && is_string($value) && str_starts_with($value,'data:image'))<div class="record-media"><img src="{{ $value }}" alt="Firma registrada"></div>
            @else<p>{{ is_array($value)?implode(', ',$value):($value!==null && $value!==''?$value:'—') }}</p>@endif
        </article>
        @empty<div class="empty-state">Este registro no contiene respuestas.</div>@endforelse
    </div></section>
    <footer><div><small>Ubicación GPS</small>@if($report->latitude)<strong>{{ $report->latitude }}, {{ $report->longitude }}</strong><a target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $report->latitude }},{{ $report->longitude }}">Abrir en Google Maps ↗</a>@else<strong>Sin ubicación registrada</strong>@endif</div><button type="button" data-close>Cerrar</button></footer>
</dialog>
@endforeach
@endsection

@push('scripts')
<script>
const recordPoints=@json($mapPoints);let centralMap,centralMarkers={};
const escapeMapText=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
function markerContent(point){return `<strong>${escapeMapText(point.form)}</strong><br><span>${escapeMapText(point.user)}</span><br><small>${escapeMapText(point.date)}</small><br><a target="_blank" rel="noopener" href="https://www.google.com/maps?q=${point.lat},${point.lng}">Abrir en Google Maps ↗</a>`}
function initGoogleRecordsMap(){if(!recordPoints.length)return;centralMap=new google.maps.Map(document.getElementById('records-map'),{mapTypeId:'hybrid',mapTypeControl:true,streetViewControl:false});const bounds=new google.maps.LatLngBounds();recordPoints.forEach(point=>{const marker=new google.maps.Marker({position:{lat:point.lat,lng:point.lng},map:centralMap,title:point.form});const info=new google.maps.InfoWindow({content:markerContent(point)});marker.addListener('click',()=>info.open({anchor:marker,map:centralMap}));centralMarkers[point.id]={marker,info};bounds.extend(marker.getPosition())});google.maps.event.addListenerOnce(centralMap,'bounds_changed',()=>{if(centralMap.getZoom()>14)centralMap.setZoom(14)});centralMap.fitBounds(bounds)}
function initFallbackRecordsMap(){if(!recordPoints.length)return;const satellite=L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{maxNativeZoom:18,maxZoom:22,attribution:'Imágenes © Esri'}),streets=L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxNativeZoom:19,maxZoom:22,attribution:'© OpenStreetMap'});centralMap=L.map('records-map',{layers:[satellite]}).setView([recordPoints[0].lat,recordPoints[0].lng],13);L.control.layers({'Satélite':satellite,'Calles':streets},null,{position:'topright'}).addTo(centralMap);const bounds=[];recordPoints.forEach(point=>{const marker=L.marker([point.lat,point.lng]).addTo(centralMap).bindPopup(markerContent(point));centralMarkers[point.id]={marker};bounds.push([point.lat,point.lng])});centralMap.fitBounds(bounds,{padding:[30,30],maxZoom:14})}
document.querySelectorAll('.locate-report').forEach(button=>button.onclick=()=>{const item=centralMarkers[button.dataset.id];if(!item)return;if(window.google?.maps){centralMap.panTo(item.marker.getPosition());centralMap.setZoom(19);item.info.open({anchor:item.marker,map:centralMap})}else{centralMap.setView(item.marker.getLatLng(),18);item.marker.openPopup()}document.querySelector('.records-map-panel').scrollIntoView({behavior:'smooth',block:'center'})});
document.querySelectorAll('.record-row').forEach(row=>{const openDetail=()=>document.getElementById('record-detail-'+row.dataset.reportId)?.showModal();row.ondblclick=event=>{if(!event.target.closest('a,button'))openDetail()};row.onkeydown=event=>{if(event.key==='Enter')openDetail()}});
document.getElementById('new-capture-button').onclick=()=>{const url=document.getElementById('new-capture-form').value;if(url)location.href=url;else document.getElementById('new-capture-form').focus()};
</script>
@if(config('services.google_maps.key'))<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google_maps.key')) }}&callback=initGoogleRecordsMap"></script>
@else<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" onload="initFallbackRecordsMap()"></script>@endif
@endpush
