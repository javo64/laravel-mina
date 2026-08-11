@extends('layouts.app')
@section('title','Productos y Servicios')
@section('content')
<div class="breadcrumb">ALMACÉN › Productos y Servicios</div>
<div class="heading">
    <div><h1>Productos y servicios</h1><p>Administra tu catálogo, precios y niveles de inventario en MySQL.</p></div>
    <div class="heading-actions">
        <button class="secondary" onclick="document.getElementById('product-categories').showModal()">▦ Categorías</button>
        <button class="secondary" onclick="document.getElementById('measurement-units').showModal()">◇ Unidades de medida</button>
        <button class="primary" onclick="document.getElementById('new-product').showModal()">＋ Nuevo producto</button>
    </div>
</div>
<div class="stats">
    <article><span>▦</span><div><small>Total de ítems</small><strong>{{ \App\Models\Product::where('is_active',true)->count() }}</strong></div></article>
    <article><span>▣</span><div><small>Productos</small><strong>{{ \App\Models\Product::where('is_active',true)->where('type','Producto')->count() }}</strong></div></article>
    <article><span>◇</span><div><small>Servicios</small><strong>{{ \App\Models\Product::where('is_active',true)->where('type','Servicio')->count() }}</strong></div></article>
    <article><span>!</span><div><small>Stock bajo</small><strong>{{ \App\Models\Product::where('is_active',true)->whereColumn('stock','<=','min_stock')->count() }}</strong></div></article>
</div>
<div class="card">
    <form class="toolbar"><label>⌕ <input name="q" value="{{ request('q') }}" placeholder="Buscar por nombre, código o categoría..."></label><button>Buscar</button></form>
    <div class="table-wrap"><table><thead><tr><th>Producto</th><th>Código</th><th>Categoría</th><th>Unidad</th><th>Stock</th><th>Precio</th><th>IGV</th><th></th></tr></thead><tbody>
    @foreach($products as $product)
        <tr><td><div class="entity"><span>{{ mb_substr($product->name,0,1) }}</span><div><strong>{{ $product->name }}</strong><small>{{ $product->type }}</small></div></div></td><td><code>{{ $product->code }}</code></td><td><em>{{ $product->category }}</em></td><td>{{ $product->unit }}</td><td>{{ $product->type==='Servicio'?'No aplica':$product->stock }}</td><td><strong>S/ {{ number_format($product->price,2) }}</strong></td><td>{{ $product->includes_tax?'Sí':'No' }}</td><td><div class="row-actions"><button onclick="event.preventDefault();document.getElementById('edit-product-{{ $product->id }}').showModal()">Editar</button><form method="post" action="{{ route('products.destroy',$product) }}">@csrf @method('DELETE')<button class="danger">Anular</button></form></div></td></tr>
        <dialog class="product-dialog" id="edit-product-{{ $product->id }}"><form method="post" action="{{ route('products.update',$product) }}">@csrf @method('PUT')<div class="modal-head product-modal-head"><span class="modal-icon">▣</span><div><h2>Editar producto o servicio</h2><p>Actualiza la información del catálogo.</p></div><button type="button" data-close>×</button></div>@include('products.form',['product'=>$product])<div class="modal-foot"><small>Los campos con * son obligatorios.</small><button type="button" data-close>Cancelar</button><button class="primary">Guardar cambios</button></div></form></dialog>
    @endforeach
    </tbody></table></div>{{ $products->links() }}
</div>

<dialog id="product-categories"><div class="modal-head"><div><h2>Registro de categorías</h2><p>Administra las categorías del catálogo.</p></div><button type="button" data-close>×</button></div>
    <form method="post" action="{{ route('product-categories.store') }}">@csrf<div class="form-grid"><label>Nombre de categoría *<input name="name" required></label><label>Descripción<input name="description"></label></div><div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">＋ Registrar categoría</button></div></form>
    <div class="manage-list">@forelse($categories as $category)<div><span class="entity"><span>{{ mb_substr($category->name,0,1) }}</span><span><strong>{{ $category->name }}</strong><small>{{ $category->description ?: 'Sin descripción' }}</small></span></span><form method="post" action="{{ route('product-categories.destroy',$category) }}">@csrf @method('DELETE')<button class="danger">Retirar</button></form></div>@empty<p>Aún no existen categorías registradas.</p>@endforelse</div>
</dialog>

<dialog id="measurement-units"><div class="modal-head"><div><h2>Registro de unidades de medida</h2><p>Administra las unidades disponibles para productos y servicios.</p></div><button type="button" data-close>×</button></div>
    <form method="post" action="{{ route('measurement-units.store') }}">@csrf<div class="form-grid"><label>Nombre de unidad *<input name="name" required placeholder="Ej. Unidad"></label><label>Símbolo *<input name="symbol" required placeholder="Ej. UND"></label></div><div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">＋ Registrar unidad</button></div></form>
    <div class="manage-list">@forelse($units as $unit)<div><span class="entity"><span>{{ mb_substr($unit->symbol,0,1) }}</span><span><strong>{{ $unit->name }}</strong><small>Símbolo: {{ $unit->symbol }}</small></span></span><form method="post" action="{{ route('measurement-units.destroy',$unit) }}">@csrf @method('DELETE')<button class="danger">Retirar</button></form></div>@empty<p>Aún no existen unidades registradas.</p>@endforelse</div>
</dialog>

<dialog class="product-dialog" id="new-product"><form method="post" action="{{ route('products.store') }}">@csrf<div class="modal-head product-modal-head"><span class="modal-icon">▣</span><div><h2>Nuevo producto o servicio</h2><p>Completa la información para agregarlo al catálogo.</p></div><button type="button" data-close>×</button></div>@include('products.form',['product'=>null])<div class="modal-foot"><small>Los campos con * son obligatorios.</small><button type="button" data-close>Cancelar</button><button class="primary">Guardar producto</button></div></form></dialog>
@endsection
