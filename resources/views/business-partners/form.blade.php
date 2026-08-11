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
</div>
