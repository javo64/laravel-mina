<div class="partner-form">
    <div class="form-section-title"><strong>Identificación</strong><span>{{ $lookup ? 'Consulta automática por DNI o RUC' : 'Datos registrados' }}</span></div>
    <label class="partner-span-4">Tipo de relación *<select name="type" required>@foreach(['Cliente','Proveedor','Cliente y proveedor'] as $type)<option {{ old('type',optional($partner)->type)===$type?'selected':'' }}>{{ $type }}</option>@endforeach</select></label>
    <label class="partner-span-3">Tipo de documento *<select name="document_type" required><option {{ old('document_type',optional($partner)->document_type)==='DNI'?'selected':'' }}>DNI</option><option {{ old('document_type',optional($partner)->document_type)==='RUC'?'selected':'' }}>RUC</option></select></label>
    <label class="partner-span-5">N.º de documento *<div class="document-lookup-input"><input inputmode="numeric" pattern="[0-9]{8}|[0-9]{11}" maxlength="11" name="document_number" required value="{{ old('document_number',optional($partner)->document_number) }}" {{ $partner?'readonly':'' }}>@if($lookup)<button type="button" class="lookup-document">⌕ Consultar</button>@endif</div>@if($lookup)<span class="lookup-status"></span>@endif</label>
    <label class="partner-span-8">Nombre completo / Razón social *<input name="name" required value="{{ old('name',optional($partner)->name) }}"></label>
    <label class="partner-span-4">Nombre comercial<input name="trade_name" value="{{ old('trade_name',optional($partner)->trade_name) }}"></label>
    <div class="form-section-title"><strong>Ubicación y contacto</strong><span>Información editable</span></div>
    <label class="partner-span-8">Dirección<input name="address" value="{{ old('address',optional($partner)->address) }}"></label>
    <label class="partner-span-4">Distrito<input name="district" value="{{ old('district',optional($partner)->district) }}"></label>
    <label class="partner-span-4">Provincia<input name="province" value="{{ old('province',optional($partner)->province) }}"></label>
    <label class="partner-span-4">Departamento<input name="department" value="{{ old('department',optional($partner)->department) }}"></label>
    <label class="partner-span-4">Teléfono<input name="phone" value="{{ old('phone',optional($partner)->phone) }}"></label>
    <label class="partner-span-6">Correo electrónico<input type="email" name="email" value="{{ old('email',optional($partner)->email) }}"></label>
    <label class="partner-active"><input type="checkbox" name="is_active" value="1" {{ optional($partner)->is_active!==false?'checked':'' }}> Registro activo</label>
    @php($accountRows = $partner && $partner->bankAccounts->isNotEmpty() ? $partner->bankAccounts : collect([null]))
    <div class="form-section-title"><strong>Cuentas bancarias</strong><span>{{ $partner ? 'Edita o agrega cuentas vinculadas a este registro.' : 'Opcional: se registrarán junto con el cliente o proveedor.' }}</span></div>
    <div class="partner-span-12 partner-bank-accounts" data-next="{{ $accountRows->count() }}">
        @foreach($accountRows as $accountIndex => $account)
        <div class="form-grid partner-bank-row">
            <input type="hidden" name="bank_accounts[{{ $accountIndex }}][id]" value="{{ $account?->id }}">
            <label>Banco<select name="bank_accounts[{{ $accountIndex }}][bank_id]"><option value="">Seleccionar banco</option>@foreach($banks as $bank)<option value="{{ $bank->id }}" {{ $account?->bank_id===$bank->id?'selected':'' }}>{{ $bank->name }}</option>@endforeach</select></label>
            <label>Tipo<select name="bank_accounts[{{ $accountIndex }}][account_type]"><option value="Cuenta Corriente" {{ $account?->account_type==='Cuenta Corriente'?'selected':'' }}>Cuenta Corriente</option><option value="Cuenta Interbancaria" {{ $account?->account_type==='Cuenta Interbancaria'?'selected':'' }}>Cuenta Interbancaria</option></select></label>
            <label>Moneda<select name="bank_accounts[{{ $accountIndex }}][currency]"><option value="PEN" {{ $account?->currency!=='USD'?'selected':'' }}>Soles (PEN)</option><option value="USD" {{ $account?->currency==='USD'?'selected':'' }}>Dólares (USD)</option></select></label>
            <label>N.º de cuenta<input name="bank_accounts[{{ $accountIndex }}][account_number]" maxlength="100" value="{{ $account?->account_number }}"></label>
            <label>Titular<input name="bank_accounts[{{ $accountIndex }}][holder_name]" maxlength="255" value="{{ $account?->holder_name }}"></label>
            <label>Estado<select name="bank_accounts[{{ $accountIndex }}][is_active]"><option value="1" {{ $account?->is_active!==false?'selected':'' }}>Activa</option><option value="0" {{ $account?->is_active===false?'selected':'' }}>Inactiva</option></select></label>
        </div>
        @endforeach
    </div>
    <div class="partner-span-12 partner-bank-actions"><button type="button" class="secondary add-partner-bank-account">＋ Agregar otra cuenta</button><small>Las cuentas activas aparecerán automáticamente en órdenes de compra.</small></div>
</div>
