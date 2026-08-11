<?php

namespace App\Http\Controllers;

use App\Models\BusinessPartner;
use App\Services\DocumentLookupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessPartnerController extends Controller
{
    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('logistics'), 403);
    }

    public function index(Request $request)
    {
        $this->allowed();
        $partners = BusinessPartner::when($request->type, fn ($query, $type) => $query->where('type', $type))
            ->when($request->q, fn ($query, $value) => $query->where(fn ($search) => $search
                ->where('document_number', 'like', "%$value%")
                ->orWhere('name', 'like', "%$value%")
                ->orWhere('trade_name', 'like', "%$value%")))
            ->latest()->paginate(12)->withQueryString();

        return view('business-partners.index', compact('partners'));
    }

    public function lookup(Request $request, DocumentLookupService $service)
    {
        $this->allowed();
        $data = $request->validate([
            'document_number' => ['required', 'digits_between:8,11', 'regex:/^(?:\d{8}|\d{11})$/'],
        ]);

        return response()->json($service->lookup($data['document_number']));
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();
        $data['is_active'] = $request->boolean('is_active', true);
        BusinessPartner::create($data);

        return redirect()->route('business-partners.index')
            ->with('success', 'Cliente o proveedor registrado correctamente.');
    }

    public function update(Request $request, BusinessPartner $businessPartner)
    {
        $this->allowed();
        $data = $this->validated($request, $businessPartner);
        $data['is_active'] = $request->boolean('is_active');
        $businessPartner->update($data);

        return back()->with('success', 'Cliente o proveedor actualizado.');
    }

    private function validated(Request $request, ?BusinessPartner $partner = null): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['Cliente', 'Proveedor', 'Cliente y proveedor'])],
            'document_type' => ['required', Rule::in(['DNI', 'RUC'])],
            'document_number' => [
                'required', 'regex:/^(?:\d{8}|\d{11})$/',
                Rule::unique('business_partners')->ignore($partner?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }
}
