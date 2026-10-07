@extends('layouts.app')
@section('title','Centro de control de almacén')
@section('content')
@php
    $totalUnits = (float) $stocks->sum('quantity');
    $productStocked = $stocks->where('quantity', '>', 0)->pluck('product_id')->unique()->count();
@endphp
<div class="breadcrumb">ALMACÉN › Centro de control</div>
<div class="heading warehouse-heading">
    <div><h1>Centro de control de almacén</h1><p>Visibilidad operativa de existencias, abastecimiento y movimientos. Las cantidades se controlan por almacén físico.</p></div>
    <div class="heading-actions"><a class="secondary" href="{{ route('inventory.index', ['tab' => 'inventario']) }}">Ver existencias</a><a class="primary" href="{{ route('product-receptions.index') }}">↓ Registrar recepción</a></div>
</div>

<section class="warehouse-kpis" aria-label="Indicadores de almacén">
    <article><span class="warehouse-kpi-icon blue">▦</span><div><small>Almacenes activos</small><strong>{{ $warehouses }}</strong><em>Ubicaciones habilitadas</em></div></article>
    <article><span class="warehouse-kpi-icon green">◈</span><div><small>Productos con existencias</small><strong>{{ $productStocked }} <i>/ {{ $products->count() }}</i></strong><em>{{ number_format($totalUnits, 0) }} unidades disponibles</em></div></article>
    <article class="{{ $criticalProducts->isNotEmpty() ? 'attention' : '' }}"><span class="warehouse-kpi-icon amber">!</span><div><small>Stock por reponer</small><strong>{{ $criticalProducts->count() }}</strong><em>Por debajo o igual al mínimo</em></div></article>
    <article><span class="warehouse-kpi-icon purple">↓</span><div><small>Recepciones pendientes</small><strong>{{ $pendingReceipts }}</strong><em>Órdenes con saldo por recibir</em></div></article>
</section>

<section class="warehouse-processes">
    <header><div><span>FLUJO OPERATIVO</span><h2>Procesos de almacén</h2><p>El flujo recomendado separa datos maestros, abastecimiento, movimientos físicos y control.</p></div></header>
    <div class="warehouse-process-grid">
        <a href="{{ route('products.index') }}"><b class="process-number">01</b><span class="process-icon">◈</span><div><strong>Maestro de productos</strong><small>Productos, servicios, unidades, categorías y stock mínimo.</small></div><i>→</i></a>
        <a href="{{ route('product-receptions.index') }}"><b class="process-number">02</b><span class="process-icon">↓</span><div><strong>Recepción de productos</strong><small>Ingreso contra orden de compra y actualización de existencias.</small></div><i>→</i></a>
        <a href="{{ route('inventory.index', ['tab' => 'movimientos']) }}"><b class="process-number">03</b><span class="process-icon">⇄</span><div><strong>Movimientos internos</strong><small>Traslados entre almacenes y devoluciones a proveedor.</small></div><i>→</i></a>
        <a href="{{ route('inventory.index', ['tab' => 'kardex']) }}"><b class="process-number">04</b><span class="process-icon">▤</span><div><strong>Control y trazabilidad</strong><small>Kardex, stock por ubicación y auditoría documental.</small></div><i>→</i></a>
    </div>
</section>

<div class="warehouse-dashboard-grid">
    <section class="card warehouse-alert-card">
        <div class="card-title"><div><h2>Alertas de reposición</h2><p>Productos que alcanzaron o bajaron de su stock mínimo.</p></div><a href="{{ route('products.index') }}">Configurar mínimos →</a></div>
        <div class="table-wrap"><table><thead><tr><th>Producto</th><th>Disponible</th><th>Mínimo</th><th>Acción recomendada</th></tr></thead><tbody>
        @forelse($criticalProducts->take(7) as $product)<tr><td><strong>{{ $product->code }} · {{ $product->name }}</strong><small>{{ $product->unit }}</small></td><td><b class="critical-number">{{ number_format($product->available_stock, 0) }}</b></td><td>{{ number_format($product->min_stock, 0) }}</td><td><a class="table-link" href="{{ route('requirements.index') }}">Generar requerimiento</a></td></tr>
        @empty<tr><td colspan="4"><div class="empty-state"><b>✓</b><p>No hay productos en nivel crítico. Mantén configurados los stocks mínimos de cada producto.</p></div></td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="card warehouse-side-card">
        <div class="card-title"><div><h2>Actividad de hoy</h2><p>Documentos físicos registrados en el día.</p></div></div>
        <div class="warehouse-activity"><div><span class="activity-icon entry">↓</span><p><b>{{ $todayEntries }}</b><small>entradas por recepción</small></p></div><div><span class="activity-icon transfer">⇄</span><p><b>{{ $todayTransfers }}</b><small>traslados internos</small></p></div></div>
        <div class="warehouse-master-links"><a href="{{ route('branches.index') }}"><span>⌂</span><div><b>Red de almacenes</b><small>Sucursales, almacenes y ubicaciones</small></div><i>→</i></a><a href="{{ route('inventory.index', ['tab' => 'inventario']) }}"><span>▦</span><div><b>Consulta de stock</b><small>Existencias por almacén físico</small></div><i>→</i></a></div>
    </section>
</div>

<section class="card warehouse-recent-card"><div class="card-title"><div><h2>Últimos movimientos</h2><p>Trazabilidad reciente del inventario.</p></div><a href="{{ route('inventory.index', ['tab' => 'kardex']) }}">Abrir kardex →</a></div><div class="table-wrap"><table><thead><tr><th>Documento</th><th>Fecha y hora</th><th>Tipo</th><th>Producto</th><th>Origen / destino</th><th>Cantidad</th></tr></thead><tbody>@forelse($recentMovements as $movement)<tr><td><code>{{ $movement->code }}</code></td><td>{{ $movement->occurred_at->format('d/m/Y H:i') }}</td><td><span class="inventory-badge {{ str($movement->type)->slug() }}">{{ $movement->type }}</span></td><td><strong>{{ $movement->product?->name ?? 'Producto eliminado' }}</strong></td><td>{{ $movement->sourceWarehouse?->name ?? '—' }} <span class="route-arrow">→</span> {{ $movement->destinationWarehouse?->name ?? '—' }}</td><td><strong>{{ number_format($movement->quantity, 0) }}</strong></td></tr>@empty<tr><td colspan="6"><div class="empty-state"><b>⇅</b><p>Aún no se registraron movimientos de inventario.</p></div></td></tr>@endforelse</tbody></table></div></section>
@endsection
