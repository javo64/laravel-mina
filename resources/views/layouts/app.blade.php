<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title') | Sistema en la nube Mina</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/mina.css') }}"><link rel="stylesheet" href="{{ asset('css/mina-enhancements.css') }}">
</head>
<body>
<div class="shell">
    <aside id="sidebar">
        <div class="brand">
            <b>F</b><strong>FABULOSA</strong>
            <button id="sidebar-collapse" class="sidebar-collapse" type="button" title="Contraer menú" aria-label="Contraer menú" aria-expanded="true"><span>‹</span></button>
            <button class="sidebar-close" type="button" aria-label="Cerrar módulos" onclick="document.body.classList.remove('nav-open')">×</button>
        </div>
        <div class="company" title="Fabulosa Company · {{ auth()->user()->branch }}"><span>FC</span><div><strong>Fabulosa Company</strong><small>{{ auth()->user()->branch }}</small></div></div>
        <nav aria-label="Módulos principales"><p>MÓDULOS</p>
            @if(auth()->user()->canAccess('products')||auth()->user()->canAccess('requirements')||auth()->user()->canAccess('approvals'))
            <div class="module" data-module="warehouse"><button class="module-title" type="button" title="Contraer Almacén" aria-expanded="true"><span class="module-icon">▦</span><span class="module-label">ALMACÉN</span><span class="module-chevron">⌃</span></button><div class="module-links">
                @if(auth()->user()->canAccess('products'))
                <a data-label="Productos y Servicios" title="Productos y Servicios" class="{{ request()->routeIs('products.*')?'active':'' }}" href="{{ route('products.index') }}"><span class="nav-icon">◈</span><span class="nav-label">Productos y Servicios</span></a>
                <a data-label="Recepción de Productos" title="Recepción de Productos" class="{{ request()->routeIs('product-receptions.*')?'active':'' }}" href="{{ route('product-receptions.index') }}"><span class="nav-icon">↓</span><span class="nav-label">Recepción de Productos</span></a>
                @endif
                @if(auth()->user()->canAccess('requirements'))<a data-label="Requerimientos" title="Requerimientos" class="{{ request()->routeIs('requirements.*')?'active':'' }}" href="{{ route('requirements.index') }}"><span class="nav-icon">▤</span><span class="nav-label">Requerimientos</span></a>@endif
                @if(auth()->user()->canAccess('approvals'))<a data-label="Aprobaciones" title="Aprobaciones" class="{{ request()->routeIs('approvals.*')?'active':'' }}" href="{{ route('approvals.index') }}"><span class="nav-icon">✓</span><span class="nav-label">Aprobaciones</span></a>@endif
            </div></div>
            @endif
            @if(auth()->user()->canAccess('logistics'))
            <div class="module" data-module="logistics"><button class="module-title" type="button" title="Contraer Logística" aria-expanded="true"><span class="module-icon">♜</span><span class="module-label">LOGÍSTICA</span><span class="module-chevron">⌃</span></button><div class="module-links">
                <a data-label="Clientes y Proveedores" title="Clientes y Proveedores" class="{{ request()->routeIs('business-partners.*')?'active':'' }}" href="{{ route('business-partners.index') }}"><span class="nav-icon">♙</span><span class="nav-label">Clientes y Proveedores</span></a>
                <a data-label="Órdenes de compra" title="Órdenes de compra" class="{{ request()->routeIs('purchase-orders.*')?'active':'' }}" href="{{ route('purchase-orders.index') }}"><span class="nav-icon">▤</span><span class="nav-label">Órdenes de compra</span></a>
            </div></div>
            @endif
            @if(auth()->user()->canAccess('daily-reports'))
            <div class="module" data-module="daily-reports"><button class="module-title" type="button" title="Contraer Parte Diario Digital" aria-expanded="true"><span class="module-icon">▤</span><span class="module-label">PARTE DIARIO DIGITAL</span><span class="module-chevron">⌃</span></button><div class="module-links">
                <a data-label="Creación de Cartillas" title="Creación de Cartillas" class="{{ request()->routeIs('daily-reports.index','daily-reports.create','daily-reports.edit','daily-reports.preview')?'active':'' }}" href="{{ route('daily-reports.index') }}"><span class="nav-icon">✚</span><span class="nav-label">Creación de Cartillas</span></a>
                <a data-label="Registro de Cartillas" title="Registro de Cartillas" class="{{ request()->routeIs('daily-reports.records','daily-reports.fill')?'active':'' }}" href="{{ route('daily-reports.records') }}"><span class="nav-icon">◫</span><span class="nav-label">Registro de Cartillas</span></a>
            </div></div>
            @endif
            @if(auth()->user()->canAccess('users'))
            <div class="module admin" data-module="administration"><button class="module-title" type="button" title="Contraer Administración" aria-expanded="true"><span class="module-icon">♙</span><span class="module-label">ADMINISTRACIÓN</span><span class="module-chevron">⌃</span></button><div class="module-links">
                <a data-label="Usuarios" title="Usuarios" class="{{ request()->routeIs('users.*')?'active':'' }}" href="{{ route('users.index') }}"><span class="nav-icon">♟</span><span class="nav-label">Usuarios</span></a>
                <a data-label="Configuración OpenAI" title="Configuración OpenAI" class="{{ request()->routeIs('settings.openai.*')?'active':'' }}" href="{{ route('settings.openai.edit') }}"><span class="nav-icon">✦</span><span class="nav-label">Configuración OpenAI</span></a>
                <a data-label="API de documentos" title="API de documentos" class="{{ request()->routeIs('settings.document-api.*')?'active':'' }}" href="{{ route('settings.document-api.edit') }}"><span class="nav-icon">⌁</span><span class="nav-label">API de documentos</span></a>
            </div></div>
            @endif
        </nav>
        <footer><span>Desarrollado por</span><strong>Javal Tecnología</strong></footer>
    </aside>
    <button class="overlay" type="button" aria-label="Cerrar menú" onclick="document.body.classList.remove('nav-open')"></button>
    <main>
        <header><button class="menu" type="button" onclick="document.body.classList.add('nav-open')">☰ <b>Módulos</b></button><label class="global-search">⌕ <input placeholder="Buscar en el sistema..."></label><div class="account"><span>{{ collect(explode(' ',auth()->user()->name))->take(2)->map(fn($w)=>mb_substr($w,0,1))->join('') }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->profile }}</small></div><form method="post" action="{{ route('logout') }}">@csrf<button>Cerrar sesión</button></form></div></header>
        <section class="content">@if(session('success'))<div class="flash">✓ {{ session('success') }}</div>@endif @if($errors->any())<div class="errors">@foreach($errors->all() as $error)<span>• {{ $error }}</span>@endforeach</div>@endif @yield('content')</section>
    </main>
</div>
<script>
(() => {
    const toggle = document.getElementById('sidebar-collapse');
    const key = 'fabulosa-sidebar-collapsed';
    const apply = collapsed => {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        toggle?.setAttribute('aria-expanded', String(!collapsed));
        toggle?.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Contraer menú');
        toggle?.setAttribute('title', collapsed ? 'Expandir menú' : 'Contraer menú');
        if (toggle) toggle.querySelector('span').textContent = collapsed ? '›' : '‹';
    };
    try { if (window.innerWidth > 800) apply(localStorage.getItem(key) === '1'); } catch (_) {}
    toggle?.addEventListener('click', () => {
        const collapsed = !document.body.classList.contains('sidebar-collapsed');
        apply(collapsed);
        try { localStorage.setItem(key, collapsed ? '1' : '0'); } catch (_) {}
    });
    document.querySelectorAll('#sidebar .module[data-module]').forEach(module => {
        const moduleToggle = module.querySelector(':scope > .module-title');
        const moduleKey = `fabulosa-module-${module.dataset.module}-collapsed`;
        const applyModule = collapsed => {
            module.classList.toggle('module-collapsed', collapsed);
            moduleToggle?.setAttribute('aria-expanded', String(!collapsed));
            moduleToggle?.setAttribute('title', `${collapsed ? 'Expandir' : 'Contraer'} ${moduleToggle?.querySelector('.module-label')?.textContent.trim() || 'módulo'}`);
            const chevron = moduleToggle?.querySelector('.module-chevron');
            if (chevron) chevron.textContent = collapsed ? '⌄' : '⌃';
        };
        try { applyModule(localStorage.getItem(moduleKey) === '1'); } catch (_) {}
        moduleToggle?.addEventListener('click', () => {
            if (document.body.classList.contains('sidebar-collapsed') && window.innerWidth > 800) {
                apply(false);
                try { localStorage.setItem(key, '0'); } catch (_) {}
                return;
            }
            const collapsed = !module.classList.contains('module-collapsed');
            applyModule(collapsed);
            try { localStorage.setItem(moduleKey, collapsed ? '1' : '0'); } catch (_) {}
        });
    });
    document.querySelectorAll('#sidebar nav a').forEach(link => link.addEventListener('click', () => document.body.classList.remove('nav-open')));
    document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
})();
</script>
@stack('scripts')
</body></html>
