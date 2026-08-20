@extends('layouts.app')
@section('title','Órdenes de compra')
@section('content')
<div class="breadcrumb">LOGÍSTICA › Órdenes de compra</div>
<div class="heading"><div><h1>Órdenes de compra</h1><p>Emite OCO u OS utilizando exclusivamente ítems aprobados.</p></div><div class="heading-actions"><button class="primary" type="button" onclick="document.getElementById('new-purchase-order').showModal()">＋ Nueva orden</button></div></div>
<div class="stats purchase-order-stats"><article><span>▤</span><div><small>Órdenes emitidas</small><strong>{{ $orders->total() }}</strong></div></article><article><span>✓</span><div><small>Ítems aprobados disponibles</small><strong>{{ $approvedRequirements->sum(fn($requirement) => $requirement->items->count()) }}</strong></div></article><article><span>♙</span><div><small>Proveedores activos</small><strong>{{ $suppliers->count() }}</strong></div></article><article><span>◫</span><div><small>Cuentas bancarias</small><strong>{{ $bankAccounts->count() }}</strong></div></article></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Código</th><th>Documento</th><th>Proveedor</th><th>Destino</th><th>Área</th><th>Moneda</th><th>Total</th><th>Estado</th></tr></thead><tbody>@forelse($orders as $order)<tr><td><code>{{ $order->code }}</code><small>{{ $order->created_at->format('d/m/Y') }}</small></td><td><strong>{{ $order->document }} {{ $order->series }}-{{ $order->number }}</strong><small>{{ $order->payment_condition }}</small></td><td><strong>{{ $order->supplier->name }}</strong><small>RUC {{ $order->supplier->document_number }}</small></td><td>{{ $order->destination_branch }}<small>{{ $order->destination_warehouse }}</small></td><td>{{ $order->area }}</td><td>{{ $order->currency==='USD'?'Dólar':'Soles' }}</td><td><strong>{{ $order->currency==='USD'?'US$':'S/' }} {{ number_format($order->total,2) }}</strong><small>{{ $order->items->count() }} ítem(s)</small></td><td><span class="badge aprobado">{{ $order->status }}</span></td></tr>@empty<tr><td colspan="8"><div class="empty-state"><b>▤</b><p>Aún no hay órdenes emitidas.</p></div></td></tr>@endforelse</tbody></table></div>{{ $orders->links() }}</div>

<dialog class="purchase-order-dialog" id="new-purchase-order"><form method="post" action="{{ route('purchase-orders.store') }}">@csrf
    <div class="modal-head product-modal-head"><span class="modal-icon">▤</span><div><h2>Nueva orden de compra / servicio</h2><p>Selecciona un requerimiento aprobado y completa las condiciones de compra.</p></div><button type="button" data-close>×</button></div>
    <div class="purchase-order-form">
        <div class="form-section-title"><strong>Destino y documento</strong><span>Datos generales de la orden</span></div>
        <label>Sucursal destino *<select name="destination_branch" required>@foreach($branches as $branch)<option value="{{ $branch }}">{{ $branch }}</option>@endforeach</select></label>
        <label>Almacén destino *<select name="destination_warehouse" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse }}">{{ $warehouse }}</option>@endforeach</select></label>
        <label>Documento *<select name="document"><option value="OCO">OCO · Orden de compra</option><option value="OS">OS · Orden de servicio</option></select></label>
        <label>Serie *<input name="series" value="001" required></label><label>Número *<input name="number" placeholder="000001" required></label>
        <div class="form-section-title"><strong>Proveedor y pago</strong><span>Selecciona registros existentes o crea uno nuevo</span></div>
        <label class="po-span-6">Proveedor (RUC · nombre) *<span class="field-with-button"><select name="supplier_id" required><option value="">Seleccionar proveedor</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->document_number }} · {{ $supplier->name }}</option>@endforeach</select><button type="button" class="new-product-inline" onclick="document.getElementById('new-order-supplier').showModal()">＋</button></span></label>
        <label class="po-span-6">Cuenta banco<span class="field-with-button"><select name="bank_account_id"><option value="">Sin cuenta registrada</option>@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->bank_name }} · {{ $account->account_type }} · {{ $account->account_number }}{{ $account->partner ? ' · '.$account->partner->name : '' }}</option>@endforeach</select><button type="button" class="new-product-inline" onclick="document.getElementById('new-bank-account').showModal()">＋</button></span></label>
        <label>Condición de pago *<select name="payment_condition"><option>001 CONTADO</option><option>002 CREDITO 07 DIAS</option><option>003 CREDITO 15 DIAS</option></select></label>
        <label>Moneda *<select name="currency" id="po-currency"><option value="PEN">Soles (PEN)</option><option value="USD">Dólar (USD)</option></select></label>
        <label>Área *<select name="area"><option>PRODUCCION</option><option>CONTABILIDAD</option><option>LOGISTICA</option></select></label>
        <label class="check po-tax"><input type="checkbox" name="tax_exempt" value="1" id="po-tax-exempt"><span><strong>Exonerado de IGV</strong><small>Al marcarlo, el total no añadirá el 18% de IGV.</small></span></label>
        <div class="requested-products-title"><div><strong>Productos aprobados</strong><span class="item-count" id="po-item-count">0 ítems</span><small>Solo se pueden agregar ítems aprobados.</small></div><div class="pull-approved"><select id="approved-requirement"><option value="">Seleccionar requerimiento aprobado</option>@foreach($approvedRequirements as $requirement)<option value="{{ $requirement->id }}">{{ $requirement->code }} · {{ $requirement->responsible }} · {{ $requirement->items->count() }} ítem(s)</option>@endforeach</select><button class="secondary" type="button" id="pull-approved-items">⇩ Jalar productos</button></div></div>
        <div class="purchase-order-items-wrap"><div class="purchase-order-item-head"><span>N°</span><span>Requerimiento</span><span>Producto aprobado</span><span>Centro de costo</span><span>Cantidad</span><span>Unidad</span><span>Precio unitario</span><span>Total</span><span></span></div><div id="purchase-order-items"></div></div>
        <div class="purchase-order-summary"><span>Subtotal <b id="po-subtotal">S/ 0.00</b></span><span>IGV (18%) <b id="po-tax">S/ 0.00</b></span><strong>Total <b id="po-total">S/ 0.00</b></strong></div>
    </div>
    <div class="modal-foot requirement-foot"><small>El precio unitario es base imponible; el IGV se calcula automáticamente salvo exoneración.</small><button type="button" data-close>Cancelar</button><button class="primary">Guardar orden</button></div>
</form></dialog>

<dialog id="new-order-supplier"><div class="modal-head"><div><h2>Nuevo proveedor</h2><p>Se agregará a Clientes y Proveedores.</p></div><button type="button" data-close>×</button></div><form method="post" action="{{ route('purchase-orders.suppliers.store') }}">@csrf<div class="form-grid"><label>RUC *<input name="document_number" inputmode="numeric" maxlength="11" required></label><label>Razón social *<input name="name" required></label><label>Teléfono<input name="phone"></label><label>Correo<input name="email" type="email"></label></div><div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">Guardar proveedor</button></div></form></dialog>
<dialog id="new-bank-account"><div class="modal-head"><div><h2>Nueva cuenta bancaria</h2><p>Registra la cuenta para seleccionarla en la orden.</p></div><button type="button" data-close>×</button></div><form method="post" action="{{ route('purchase-orders.bank-accounts.store') }}">@csrf<div class="form-grid"><label>Proveedor asociado<select name="business_partner_id"><option value="">No asociado</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label><label>Tipo de cuenta *<select name="account_type"><option>Cuenta Corriente</option><option>Cuenta Interbancaria</option></select></label><label>Número de cuenta *<input name="account_number" required></label><label>Banco *<input name="bank_name" required></label><label>Titular<input name="holder_name"></label><label>Moneda *<select name="currency"><option value="PEN">Soles</option><option value="USD">Dólar</option></select></label></div><div class="modal-foot"><button type="button" data-close>Cancelar</button><button class="primary">Guardar cuenta</button></div></form></dialog>
@endsection
@php
    $approvedOrderData = $approvedRequirements->map(function ($requirement) {
        return [
            'id' => $requirement->id,
            'code' => $requirement->code,
            'area' => $requirement->area,
            'items' => $requirement->items->map(function ($item) {
                return [
                    'id' => $item->id, 'name' => $item->product_name, 'description' => $item->description,
                    'quantity' => (float) $item->quantity, 'unit' => $item->unit,
                    'price' => (float) optional($item->product)->price,
                ];
            })->values(),
        ];
    })->values();
@endphp
@push('scripts')
<script>
(() => {
    const data = @json($approvedOrderData);
    const dialog=document.getElementById('new-purchase-order'), rows=document.getElementById('purchase-order-items'), selector=document.getElementById('approved-requirement');
    const money=value => `${document.getElementById('po-currency').value==='USD'?'US$':'S/'} ${Number(value||0).toFixed(2)}`;
    const refresh=()=>{const exempt=document.getElementById('po-tax-exempt').checked;let subtotal=0;[...rows.children].forEach((row,index)=>{row.querySelector('.po-row-number').textContent=index+1;const quantity=Number(row.querySelector('[name$="[quantity]"]').value)||0,price=Number(row.querySelector('[name$="[unit_price]"]').value)||0,total=quantity*price;row.querySelector('.po-line-total').textContent=money(total);subtotal+=total;});const tax=exempt?0:subtotal*.18;document.getElementById('po-subtotal').textContent=money(subtotal);document.getElementById('po-tax').textContent=money(tax);document.getElementById('po-total').textContent=money(subtotal+tax);document.getElementById('po-item-count').textContent=`${rows.children.length} ${rows.children.length===1?'ítem':'ítems'}`};
    const add=item=>{if(rows.querySelector(`[data-source="${item.id}"]`))return;const index=rows.children.length;const row=document.createElement('div');row.className='purchase-order-item-row';row.dataset.source=item.id;row.innerHTML=`<span class="po-row-number"></span><input value="${item.code}" readonly><input value="${item.name}" title="${item.description||''}" readonly><input name="items[${index}][cost_center]" value="${item.area||'LOGISTICA'}" required><input type="number" step="0.01" min="0.01" max="${item.quantity}" name="items[${index}][quantity]" value="${item.quantity}" required><input value="${item.unit}" readonly><input type="number" step="0.01" min="0" name="items[${index}][unit_price]" value="${item.price||0}" required><strong class="po-line-total"></strong><input type="hidden" name="items[${index}][requirement_item_id]" value="${item.id}"><button type="button" class="remove-po-item">×</button>`;row.querySelectorAll('input[type="number"]').forEach(input=>input.addEventListener('input',refresh));row.querySelector('.remove-po-item').addEventListener('click',()=>{row.remove();renumber();refresh()});rows.appendChild(row);};
    const renumber=()=>[...rows.children].forEach((row,index)=>row.querySelectorAll('[name]').forEach(input=>input.name=input.name.replace(/items\[\d+\]/,`items[${index}]`)));
    document.getElementById('pull-approved-items').addEventListener('click',()=>{const requirement=data.find(item=>String(item.id)===selector.value);if(!requirement)return;requirement.items.forEach(item=>add({...item,code:requirement.code,area:requirement.area}));renumber();refresh()});
    document.getElementById('po-tax-exempt').addEventListener('change',refresh);document.getElementById('po-currency').addEventListener('change',refresh);dialog.addEventListener('close',refresh);refresh();
})();
</script>
@endpush
