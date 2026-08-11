@extends('layouts.app')
@section('title','Configuración OpenAI')
@section('content')
<div class="breadcrumb">ADMINISTRACIÓN › Configuración OpenAI</div>
<div class="heading">
    <div><h1>Configuración OpenAI</h1><p>Administra la credencial usada para leer guías, facturas y órdenes.</p></div>
</div>

<div class="openai-settings-grid">
    <section class="card openai-settings-card">
        <div class="settings-card-head">
            <span class="openai-logo">AI</span>
            <div><h2>Credenciales de la API</h2><p>La clave se cifra antes de guardarse en MySQL y nunca vuelve a mostrarse.</p></div>
            <span class="credential-status {{ $setting->hasApiKey() && $setting->is_active ? 'configured' : '' }}">
                {{ $setting->hasApiKey() ? ($setting->is_active ? 'Configurado' : 'Desactivado') : 'Sin configurar' }}
            </span>
        </div>
        <form method="post" action="{{ route('settings.openai.update') }}" class="openai-settings-form">@csrf @method('PUT')
            <label>Clave secreta de OpenAI
                <div class="secret-input">
                    <input id="openai-api-key" type="password" name="api_key" autocomplete="new-password" placeholder="{{ $setting->hasApiKey() ? '•••••••••••••••••••••••• (dejar vacío para conservar)' : 'sk-proj-...' }}">
                    <button type="button" id="toggle-openai-key" aria-label="Mostrar u ocultar clave">◉</button>
                </div>
                <small>{{ $setting->hasApiKey() ? 'Ya existe una clave guardada. Escribe otra únicamente para reemplazarla.' : 'La clave es obligatoria para activar la lectura automática.' }}</small>
            </label>
            <label>Modelo
                <select name="model" required><option value="gpt-5.6-sol" selected>gpt-5.6-sol</option></select>
                <small>Modelo configurado para analizar documentos e imágenes.</small>
            </label>
            <label class="openai-active-option">
                <input type="checkbox" name="is_active" value="1" {{ $setting->is_active ? 'checked' : '' }}>
                <span><strong>Integración activa</strong><small>Permitir que el sistema envíe documentos a OpenAI para su análisis.</small></span>
            </label>
            <div class="settings-actions"><button class="primary">Guardar configuración</button></div>
        </form>
    </section>

    <aside class="openai-security-card">
        <h3>Seguridad</h3>
        <ul>
            <li>La clave se almacena cifrada mediante la clave interna de Laravel.</li>
            <li>No se incluye en archivos descargables ni se muestra a otros usuarios.</li>
            <li>Solo Administración puede consultar o cambiar esta configuración.</li>
            <li>Los documentos se analizarán únicamente cuando el usuario lo solicite.</li>
        </ul>
        @if($setting->updated_at)
            <div class="settings-audit"><small>Última actualización</small><strong>{{ $setting->updated_at->format('d/m/Y H:i') }}</strong><span>{{ $setting->updater?->name ?? 'Sistema' }}</span></div>
        @endif
    </aside>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const input = document.getElementById('openai-api-key');
    document.getElementById('toggle-openai-key').addEventListener('click', () => {
        input.type = input.type === 'password' ? 'text' : 'password';
    });
})();
</script>
@endpush
