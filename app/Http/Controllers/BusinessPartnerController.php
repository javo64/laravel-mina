<?php

namespace App\Http\Controllers;

use App\Models\BusinessPartner;
use App\Models\ProductReception;
use App\Models\Bank;
use App\Models\BankAccount;
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

        $suppliers = BusinessPartner::where('is_active', true)->whereIn('type', ['Proveedor','Cliente y proveedor'])->orderBy('name')->get();
        $banks = Bank::withCount(['accounts' => fn ($query) => $query->where('is_active', true)])->where('is_active', true)->orderBy('name')->get();
        $bankAccounts = BankAccount::with(['partner','bank'])->where('is_active', true)->latest()->get();
        return view('business-partners.index', compact('partners','suppliers','banks','bankAccounts'));
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

    public function destroy(BusinessPartner $businessPartner)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if (ProductReception::where('supplier', $businessPartner->name)->exists()) {
            return back()->withErrors('No se puede eliminar el cliente o proveedor porque está vinculado a una recepción de productos.');
        }
        $businessPartner->delete();

        return back()->with('success', 'Cliente o proveedor eliminado correctamente.');
    }

    public function storeBank(Request $request)
    {
        $this->allowed();
        $data = $request->validate(['name'=>['required','max:150','unique:banks,name']]);
        $data['code'] = 'BAN-'.str_pad((string) ((Bank::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT);
        $bank = Bank::create([...$data,'is_active'=>true]);
        if ($request->expectsJson()) return response()->json(['id'=>$bank->id,'name'=>$bank->name,'code'=>$bank->code], 201);
        return back()->with('success','Banco registrado correctamente.');
    }

    public function storeBankAccount(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'business_partner_id'=>['required', Rule::exists('business_partners','id')->where('is_active',true)],
            'bank_id'=>['required', Rule::exists('banks','id')->where('is_active',true)],
            'account_type'=>['required', Rule::in(['Cuenta Corriente','Cuenta Interbancaria'])],
            'currency'=>['required', Rule::in(['PEN','USD'])], 'account_number'=>['required','max:100','unique:bank_accounts,account_number'], 'holder_name'=>['nullable','max:255'],
        ]);
        $data['bank_name'] = Bank::findOrFail($data['bank_id'])->name;
        $account = BankAccount::create([...$data,'is_active'=>true]);
        $account->load(['partner','bank']);
        if ($request->expectsJson()) return response()->json(['id'=>$account->id,'partner'=>$account->partner->name,'bank'=>$account->bank->name,'type'=>$account->account_type,'currency'=>$account->currency,'number'=>$account->account_number,'holder'=>$account->holder_name], 201);
        return back()->with('success','Cuenta bancaria registrada para el proveedor.');
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
