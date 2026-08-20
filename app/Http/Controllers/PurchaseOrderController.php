<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BusinessPartner;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Requirement;
use App\Models\RequirementItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\PurchaseOrderPdfGenerator;

class PurchaseOrderController extends Controller
{
    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('logistics'), 403);
    }

    public function index()
    {
        $this->allowed();
        $orders = PurchaseOrder::with(['supplier', 'items'])->latest()->paginate(12);
        $suppliers = BusinessPartner::where('is_active', true)
            ->whereIn('type', ['Proveedor', 'Cliente y proveedor'])
            ->orderBy('name')->get();
        $bankAccounts = BankAccount::with('partner')->where('is_active', true)->orderBy('bank_name')->get();
        $approvedRequirements = Requirement::with(['items' => fn ($query) => $query->where('approval_status', 'Aprobado')->with('product')])
            ->whereHas('items', fn ($query) => $query->where('approval_status', 'Aprobado'))
            ->latest('requested_at')->get();
        $branches = array_values(array_unique(array_filter(array_merge(['Sucursal principal'], auth()->user()->newQuery()->pluck('branch')->all()))));
        $warehouses = array_values(array_unique(array_filter(array_merge(['Almacén principal'], Product::where('is_active', true)->pluck('warehouse')->all()))));

        return view('purchase-orders.index', compact('orders', 'suppliers', 'bankAccounts', 'approvedRequirements', 'branches', 'warehouses'));
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'destination_branch' => ['required', 'max:255'],
            'destination_warehouse' => ['required', 'max:255'],
            'document' => ['required', Rule::in(['OCO', 'OS'])],
            'series' => ['required', Rule::in($this->availableSeries($request->document))],
            'supplier_id' => ['required', Rule::exists('business_partners', 'id')->where('is_active', true)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('is_active', true)],
            'payment_condition' => ['required', Rule::in(['001 CONTADO', '002 CREDITO 07 DIAS', '003 CREDITO 15 DIAS'])],
            'currency' => ['required', Rule::in(['PEN', 'USD'])],
            'area' => ['required', Rule::in(['PRODUCCION', 'CONTABILIDAD', 'LOGISTICA'])],
            'tax_exempt' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.requirement_item_id' => ['required', 'distinct', Rule::exists('requirement_items', 'id')->where('approval_status', 'Aprobado')],
            'items.*.cost_center' => ['required', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data): void {
            $lines = collect($data['items'])->map(function (array $line) {
                $source = RequirementItem::with('product')->where('approval_status', 'Aprobado')->findOrFail($line['requirement_item_id']);
                if ((float) $line['quantity'] > (float) $source->quantity) {
                    abort(422, "La cantidad de {$source->product_name} no puede superar la aprobada.");
                }
                return ['source' => $source, 'cost_center' => $line['cost_center'], 'quantity' => (float) $line['quantity'], 'unit_price' => (float) $line['unit_price']];
            });
            $subtotal = $lines->sum(fn ($line) => $line['quantity'] * $line['unit_price']);
            $tax = ! empty($data['tax_exempt']) ? 0 : round($subtotal * .18, 2);
            $number = $this->nextNumber($data['document'], $data['series'], true);
            $order = PurchaseOrder::create([
                ...collect($data)->except('items')->all(),
                'tax_exempt' => ! empty($data['tax_exempt']),
                'number' => $number,
                'code' => $data['document'].'-'.$data['series'].'-'.$number,
                'subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax,
                'status' => 'Emitida', 'created_by' => auth()->id(),
            ]);
            foreach ($lines as $line) {
                $source = $line['source'];
                $order->items()->create([
                    'requirement_item_id' => $source->id, 'product_id' => $source->product_id,
                    'product_name' => $source->product_name, 'description' => $source->description,
                    'cost_center' => $line['cost_center'], 'quantity' => $line['quantity'],
                    'unit' => $source->unit, 'unit_price' => $line['unit_price'],
                    'total' => $line['quantity'] * $line['unit_price'],
                ]);
            }
        });

        return redirect()->route('purchase-orders.index')->with('success', 'Orden registrada correctamente.');
    }

    public function nextCorrelative(Request $request)
    {
        $this->allowed();
        $document = $request->validate(['document' => ['required', Rule::in(['OCO', 'OS'])]])['document'];
        $series = $request->validate(['series' => ['required', Rule::in($this->availableSeries($document))]])['series'];

        return response()->json(['number' => $this->nextNumber($document, $series)]);
    }

    public function pdf(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderPdfGenerator $generator)
    {
        $this->allowed();
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        $filename = strtolower($purchaseOrder->document).'-'.$purchaseOrder->series.'-'.$purchaseOrder->number.'.pdf';

        return response($generator->render($purchaseOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    public function storeSupplier(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'document_number' => ['required', 'digits:11', 'unique:business_partners,document_number'],
            'name' => ['required', 'max:255'], 'phone' => ['nullable', 'max:50'], 'email' => ['nullable', 'email', 'max:255'],
        ]);
        BusinessPartner::create([...$data, 'type' => 'Proveedor', 'document_type' => 'RUC', 'is_active' => true, 'created_by' => auth()->id()]);
        return back()->with('success', 'Proveedor registrado. Ya puedes seleccionarlo en la orden.');
    }

    public function storeBankAccount(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'business_partner_id' => ['nullable', 'exists:business_partners,id'],
            'account_type' => ['required', Rule::in(['Cuenta Corriente', 'Cuenta Interbancaria'])],
            'account_number' => ['required', 'max:100'], 'bank_name' => ['required', 'max:255'],
            'holder_name' => ['nullable', 'max:255'], 'currency' => ['required', Rule::in(['PEN', 'USD'])],
        ]);
        BankAccount::create([...$data, 'is_active' => true]);
        return back()->with('success', 'Cuenta bancaria registrada. Ya puedes seleccionarla en la orden.');
    }

    private function availableSeries(?string $document): array
    {
        return $document === 'OS' ? ['003', '004'] : ['001', '002'];
    }

    private function nextNumber(string $document, string $series, bool $lock = false): string
    {
        $query = PurchaseOrder::where('document', $document)->where('series', $series);
        if ($lock) {
            $query->lockForUpdate();
        }
        $last = $query->pluck('number')->map(fn ($number) => (int) $number)->max() ?? 0;

        return str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }
}
