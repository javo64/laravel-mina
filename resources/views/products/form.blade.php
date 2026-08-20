@php
    $selectedCategory = old('category', optional($product)->category);
    $selectedUnit = old('unit', optional($product)->unit);
@endphp
<div class="product-form">
    <div class="form-section-title"><strong>Información básica</strong><span>Datos principales del ítem</span></div>
    <label class="span-4">Tipo de ítem *<select name="type" required><option value="Producto" {{ old('type',optional($product)->type ?? 'Producto')==='Producto'?'selected':'' }}>Producto</option><option value="Servicio" {{ old('type',optional($product)->type)==='Servicio'?'selected':'' }}>Servicio</option></select></label>
    <label class="span-8">Nombre *<input name="name" required value="{{ old('name',optional($product)->name) }}" placeholder="Ej. Polo básico algodón"></label>
    <label class="span-4">Nombre secundario *<input name="secondary_name" required value="{{ old('secondary_name',optional($product)->secondary_name) }}" placeholder="Nombre alternativo"></label>
    <label class="span-8">Descripción *<textarea name="description" required rows="2" placeholder="Añade una breve descripción...">{{ old('description',optional($product)->description) }}</textarea></label>
    <label class="span-4">Categoría *<select name="category" required><option value="">Seleccionar categoría</option>@foreach($categories as $category)<option value="{{ $category->name }}" {{ $selectedCategory===$category->name?'selected':'' }}>{{ $category->name }}</option>@endforeach</select></label>
    <label class="span-4">Unidad de medida *<select name="unit" required><option value="">Seleccionar unidad</option>@foreach($units as $unit)<option value="{{ $unit->name }}" {{ $selectedUnit===$unit->name?'selected':'' }}>{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</select></label>

    <div class="form-section-title inventory-title"><strong>Precio e inventario</strong><span>Define el valor y las existencias iniciales</span></div>
    <label class="span-3">Moneda<select name="currency"><option value="PEN" {{ old('currency',optional($product)->currency ?? 'PEN')==='PEN'?'selected':'' }}>Soles (PEN)</option><option value="USD" {{ old('currency',optional($product)->currency)==='USD'?'selected':'' }}>Dólares (USD)</option></select></label>
    <label class="span-3">Precio de venta *<span class="price-input"><b>S/</b><input type="number" step="0.01" min="0.01" name="price" required value="{{ old('price',optional($product)->price ?? '') }}"></span></label>
    <label class="span-3">Stock inicial *<input type="number" min="1" name="stock" value="{{ old('stock',optional($product)->stock ?? 1) }}"></label>
    <label class="span-3">Stock mínimo<input type="number" min="0" name="min_stock" value="{{ old('min_stock',optional($product)->min_stock ?? 1) }}"></label>
    <label class="span-3">Código interno<input value="{{ optional($product)->code ?: 'Se generará automáticamente' }}" readonly></label>
    <label class="span-3">Código de barras *<input name="barcode" required value="{{ old('barcode',optional($product)->barcode) }}" placeholder="Escanea o escribe el código"></label>
    <label class="span-3">Almacén<select name="warehouse" required><option {{ old('warehouse',optional($product)->warehouse ?? 'Almacén principal')==='Almacén principal'?'selected':'' }}>Almacén principal</option></select></label>
    <label class="span-3">Tipo de afectación<select name="tax_affectation" required><option {{ old('tax_affectation',optional($product)->tax_affectation ?? 'Gravado - Operación onerosa')==='Gravado - Operación onerosa'?'selected':'' }}>Gravado - Operación onerosa</option><option>Exonerado</option><option>Inafecto</option></select></label>

    <label class="option-card span-4"><input type="checkbox" name="includes_tax" value="1" {{ ($product ? optional($product)->includes_tax : true)?'checked':'' }}><span><strong>Incluye IGV</strong><small>El precio ya contiene el impuesto</small></span></label>
</div>
