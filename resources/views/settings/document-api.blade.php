@extends('layouts.app')
@section('title','API de documentos')
@section('content')
<div class="breadcrumb">ADMINISTRACIÓN › API de documentos</div>
<div class="heading"><div><h1>API de consulta DNI/RUC</h1><p>Configura el servicio externo para consultar clientes y proveedores.</p></div></div>
<div class="openai-settings-grid">
    <section class="card openai-settings-card">
        <div class="settings-card-head">
            <span class="openai-logo">API</span>
            <div><h2>Credenciales del servicio</h2><p>Admite una URL base como <code>https://apiperu.dev</code> o una ruta con <code>{document}</code>.</p></div>
            <span class="credential-status {{ $setting->hasToken() && $setting->is_active ? 'configured' : '' }}">{{ $setting->hasToken() ? ($setting->is_active ? 'Configurado' : 'Desactivado') : 'Sin configurar' }}</span>
        </div>
        <form method="post" action="{{ route('settings.document-api.update') }}" class="openai-settings-form">@csrf @method('PUT')
            <label>URL de consulta *
                <input type="url" name="url" required value="{{ old('url',$setting->url) }}" placeholder="https://apiperu.dev">
                <small>Para API Perú usa https://apiperu.dev. Otros proveedores pueden usar {type} y {document} en la ruta.</small>
            </label>
            <label>Token de acceso
                <div class="secret-input"><input id="document-api-token" type="password" name="token" autocomplete="new-password" placeholder="{{ $setting->hasToken() ? '•••••••••••• (dejar vacío para conservar)' : 'Token proporcionado por la API' }}"><button type="button" id="toggle-document-token">◉</button></div>
                <small>{{ $setting->hasToken() ? 'Ya existe un token cifrado. Escribe otro solo para reemplazarlo.' : 'El token se guardará cifrado en MySQL.' }}</small>
            </label>
            <label class="openai-active-option"><input type="checkbox" name="is_active" value="1" {{ $setting->is_active ? 'checked' : '' }}><span><strong>Integración activa</strong><small>Permitir consultas de DNI y RUC desde Logística.</small></span></label>
            <div class="settings-actions"><button class="primary">Guardar configuración</button></div>
        </form>
    </section>
    <aside class="openai-security-card">
        <h3>Funcionamiento</h3>
        <ul><li>El DNI debe contener 8 dígitos.</li><li>El RUC debe contener 11 dígitos.</li><li>El token se envía como <strong>Bearer Token</strong>.</li><li>La respuesta se utiliza para completar el formulario; el usuario confirma antes de guardar.</li></ul>
        @if($setting->updated_at)<div class="settings-audit"><small>Última actualización</small><strong>{{ $setting->updated_at->format('d/m/Y H:i') }}</strong><span>{{ $setting->updater?->name ?? 'Sistema' }}</span></div>@endif
    </aside>
</div>
@endsection
@push('scripts')
<script>(()=>{const input=document.getElementById('document-api-token');document.getElementById('toggle-document-token').addEventListener('click',()=>input.type=input.type==='password'?'text':'password')})();</script>
@endpush
