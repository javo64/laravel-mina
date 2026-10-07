<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Bank;
use App\Models\BusinessPartner;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderQuotation;
use App\Models\Requirement;
use App\Models\RequirementItem;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Services\PurchaseOrderPdfGenerator;
use Throwable;

class PurchaseOrderController extends Controller
{
    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('logistics.purchase-orders'), 403);
    }

    public function index()
    {
        $this->allowed();
        $orders = PurchaseOrder::with(['supplier', 'items', 'quotations'])->latest()->paginate(12);
        $suppliers = BusinessPartner::where('is_active', true)
            ->orderBy('name')->get();
        $bankAccounts = BankAccount::with(['partner','bank'])->where('is_active', true)->orderBy('bank_name')->get();
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $approvedRequirements = Requirement::with(['items' => fn ($query) => $query->whereIn('approval_status', ['Aprobado', 'Aprobado parcial'])
            ->whereDoesntHave('purchaseOrderItems')
            ->whereHas('quotationProcesses', fn ($processes) => $processes->where('status', 'Aprobada')->whereHas('winner'))
            ->with(['product', 'quotationProcesses' => fn ($processes) => $processes->where('status', 'Aprobada')->with('winner.supplier')])])
            ->whereHas('items', fn ($query) => $query->whereIn('approval_status', ['Aprobado', 'Aprobado parcial'])->whereDoesntHave('purchaseOrderItems')->whereHas('quotationProcesses', fn ($processes) => $processes->where('status', 'Aprobada')->whereHas('winner')))
            ->latest('requested_at')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->with('branch')->orderBy('name')->get();

        return view('purchase-orders.index', compact('orders', 'suppliers', 'bankAccounts', 'banks', 'approvedRequirements', 'branches', 'warehouses'));
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'destination_branch' => ['required', 'max:255', Rule::exists('branches','name')->where('is_active',true)],
            'destination_warehouse' => ['required', 'max:255', Rule::exists('warehouses','name')->where('is_active',true)],
            'document' => ['required', Rule::in(['OCO', 'OS'])],
            'series' => ['required', Rule::in($this->availableSeries($request->document))],
            'supplier_id' => ['required', Rule::exists('business_partners', 'id')->where('is_active', true)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('is_active', true)],
            'payment_condition' => ['required', Rule::in(['001 CONTADO', '002 CREDITO 07 DIAS', '003 CREDITO 15 DIAS'])],
            'currency' => ['required', Rule::in(['PEN', 'USD'])],
            'area' => ['required', Rule::in(['PRODUCCION', 'CONTABILIDAD', 'LOGISTICA'])],
            'tax_exempt' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.requirement_item_id' => ['required', 'distinct', Rule::exists('requirement_items', 'id')->where(fn ($query) => $query->whereIn('approval_status', ['Aprobado', 'Aprobado parcial']))],
            'items.*.cost_center' => ['required', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ]);

        if (! empty($data['bank_account_id']) && ! BankAccount::where('id', $data['bank_account_id'])->where('business_partner_id', $data['supplier_id'])->exists()) {
            return back()->withErrors(['bank_account_id' => 'La cuenta bancaria seleccionada no pertenece al proveedor elegido.'])->withInput();
        }
        if (! Warehouse::where('name', $data['destination_warehouse'])->whereHas('branch', fn ($query) => $query->where('name', $data['destination_branch']))->exists()) {
            return back()->withErrors(['destination_warehouse' => 'El almacén debe pertenecer a la sucursal destino seleccionada.'])->withInput();
        }

        try {
            DB::transaction(function () use ($data): void {
                $lines = collect($data['items'])->map(function (array $line) use ($data) {
                $source = RequirementItem::with('product')->whereIn('approval_status', ['Aprobado', 'Aprobado parcial'])->lockForUpdate()->findOrFail($line['requirement_item_id']);
                $process = $source->quotationProcesses()->where('status', 'Aprobada')->with('winner')->first();
                if(!$process || $process->status!=='Aprobada' || !$process->winner || (int)$process->winner->supplier_id!==(int)$data['supplier_id']) abort(422,'El proveedor seleccionado no corresponde a la cotización ganadora aprobada del requerimiento.');
                if ($source->purchaseOrderItems()->exists()) {
                    abort(422, "El ítem {$source->product_name} ya fue utilizado en una orden y no puede volver a seleccionarse.");
                }
                if ((float) $line['quantity'] > (float) ($source->approved_quantity ?? $source->quantity)) {
                    abort(422, "La cantidad de {$source->product_name} no puede superar la aprobada.");
                }
                return ['source' => $source, 'winning_quote'=>$process->winner, 'cost_center' => $line['cost_center'], 'quantity' => (float) $line['quantity'], 'unit_price' => (float) $line['unit_price']];
            });
                $subtotal = $lines->sum(fn ($line) => $line['quantity'] * $line['unit_price']);
                $tax = ! empty($data['tax_exempt']) ? 0 : round($subtotal * .18, 2);
                $number = $this->nextNumber($data['document'], $data['series'], true);
                $order = PurchaseOrder::create([
                ...collect($data)->except(['items'])->all(),
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
                foreach ($lines->pluck('winning_quote')->unique('id') as $quotation) {
                    $order->quotations()->create([
                        'requirement_quotation_id'=>$quotation->id,'path'=>$quotation->path,
                        'original_name'=>$quotation->original_name,'mime_type'=>$quotation->mime_type,'size'=>$quotation->size,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            throw $exception;
        }

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
        abort_unless(auth()->user()->canAccess('logistics.purchase-orders') || auth()->user()->canAccess('warehouse.approvals'), 403);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        $filename = strtolower($purchaseOrder->document).'-'.$purchaseOrder->series.'-'.$purchaseOrder->number.'.pdf';

        return response($generator->render($purchaseOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    public function quotation(PurchaseOrderQuotation $purchaseOrderQuotation)
    {
        abort_unless(auth()->user()->canAccess('logistics.purchase-orders') || auth()->user()->canAccess('warehouse.approvals'), 403);
        abort_unless(Storage::disk('local')->exists($purchaseOrderQuotation->path), 404);

        return response()->file(Storage::disk('local')->path($purchaseOrderQuotation->path), [
            'Content-Type' => $purchaseOrderQuotation->mime_type,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if ($purchaseOrder->receptions()->exists()) {
            return back()->withErrors('Primero debes revertir las recepciones vinculadas a esta orden.');
        }
        $purchaseOrder->delete();
        return back()->with('success', 'Orden eliminada definitivamente. Los ítems aprobados vuelven a estar disponibles.');
    }

    public function storeSupplier(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'document_number' => ['required', 'digits:11', 'unique:business_partners,document_number'],
            'name' => ['required', 'max:255'], 'phone' => ['nullable', 'max:50'], 'email' => ['nullable', 'email', 'max:255'],
        ]);
        $supplier = BusinessPartner::create([...$data, 'type' => 'Proveedor', 'document_type' => 'RUC', 'is_active' => true, 'created_by' => auth()->id()]);
        if ($request->expectsJson()) {
            return response()->json(['id'=>$supplier->id, 'document_number'=>$supplier->document_number, 'name'=>$supplier->name], 201);
        }
        return back()->with('success', 'Proveedor registrado. Ya puedes seleccionarlo en la orden.');
    }

    public function storeBankAccount(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'business_partner_id' => ['required', Rule::exists('business_partners','id')->where('is_active',true)],
            'bank_id' => ['required', Rule::exists('banks','id')->where('is_active',true)],
            'account_type' => ['required', Rule::in(['Cuenta Corriente', 'Cuenta Interbancaria'])],
            'account_number' => ['required', 'max:100', 'unique:bank_accounts,account_number'],
            'holder_name' => ['nullable', 'max:255'], 'currency' => ['required', Rule::in(['PEN', 'USD'])],
        ]);
        $data['bank_name'] = Bank::findOrFail($data['bank_id'])->name;
        $account = BankAccount::create([...$data, 'is_active' => true]);
        if ($request->expectsJson()) {
            return response()->json([
                'id'=>$account->id, 'business_partner_id'=>$account->business_partner_id,
                'label'=>$account->bank_name.' · '.$account->account_type.' · '.($account->currency === 'USD' ? 'Dólares' : 'Soles').' · '.$account->account_number,
            ], 201);
        }
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
