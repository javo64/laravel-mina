<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReception;
use App\Models\BusinessPartner;
use App\Models\Warehouse;
use App\Models\InventoryStock;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\OpenAiDocumentReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductReceptionController extends Controller
{
    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('warehouse.receptions'), 403);
    }

    public function index(Request $request)
    {
        $this->allowed();
        $receptions = ProductReception::with(['items', 'receiver', 'purchaseOrder.supplier'])
            ->when($request->q, fn ($query, $value) => $query->where(fn ($search) => $search
                ->where('code', 'like', "%$value%")
                ->orWhere('supplier', 'like', "%$value%")
                ->orWhere('guide_number', 'like', "%$value%")
                ->orWhere('invoice_number', 'like', "%$value%")
                ->orWhere('order_number', 'like', "%$value%")))
            ->latest('received_at')->latest('id')->paginate(10)->withQueryString();
        $supplierCount = BusinessPartner::where('is_active', true)
            ->whereIn('type', ['Proveedor', 'Cliente y proveedor'])
            ->count();
        $nextCode = $this->formatCode((ProductReception::max('id') ?? 0) + 1);

        $receivableOrders = PurchaseOrder::with([
                'supplier',
                'items' => fn ($query) => $query->whereNotNull('product_id')
                    ->whereHas('product', fn ($product) => $product->where('type', 'Producto'))
                    ->with('product'),
                'receptions',
            ])
            ->where('document', 'OCO')
            ->where('status', 'Aprobada')
            ->where('receipt_status', '!=', 'Completa')
            ->whereHas('items', fn ($query) => $query->whereNotNull('product_id')
                ->whereColumn('received_quantity', '<', 'quantity')
                ->whereHas('product', fn ($product) => $product->where('type', 'Producto')))
            ->latest()->get();
        $warehouses = Warehouse::with('branch')->where('is_active',true)->orderBy('name')->get();
        return view('product-receptions.index', compact('receptions', 'receivableOrders', 'supplierCount', 'nextCode', 'warehouses'));
    }

    public function searchSuppliers(Request $request)
    {
        $this->allowed();
        $validated = $request->validate(['q'=>['nullable','string','max:100']]);
        $query = trim((string) ($validated['q'] ?? ''));
        if (mb_strlen($query) < 2) return response()->json([]);

        return response()->json(BusinessPartner::where('is_active', true)
            ->whereIn('type', ['Proveedor', 'Cliente y proveedor'])
            ->where(fn ($search) => $search->where('name', 'like', "%{$query}%")
                ->orWhere('trade_name', 'like', "%{$query}%")
                ->orWhere('document_number', 'like', "%{$query}%"))
            ->orderBy('name')->limit(8)->get(['id','document_number','name','trade_name']));
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'purchase_order_id' => ['required', Rule::exists('purchase_orders','id')->where(fn ($query) => $query->where('document','OCO')->where('status','Aprobada'))],
            'received_at' => ['required', 'date'],
            'guide_number' => ['nullable', 'string', 'max:100'],
            'guide_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'guide_camera' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'invoice_camera' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'order_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'order_camera' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = PurchaseOrder::with('supplier')->whereKey($data['purchase_order_id'])
            ->where('document','OCO')->where('status','Aprobada')->firstOrFail();
        if ($order->receipt_status === 'Completa') {
            throw ValidationException::withMessages(['purchase_order_id' => 'Esta orden ya fue recibida completamente.']);
        }

        $storedFiles = [];
        foreach (['guide', 'invoice', 'order'] as $document) {
            $field = $request->hasFile($document.'_camera') ? $document.'_camera' : $document.'_file';
            if ($request->hasFile($field)) {
                $storedFiles[$document.'_file'] = $request->file($field)->store('recepciones', 'public');
            }
        }

        $warehouse = Warehouse::where('name',$order->destination_warehouse)->where('is_active',true)->firstOrFail();
        try {
            DB::transaction(function () use ($data, $storedFiles, $warehouse, $order) {
            $lockedOrder = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status !== 'Aprobada' || $lockedOrder->receipt_status === 'Completa') {
                throw ValidationException::withMessages(['purchase_order_id' => 'La orden ya no está disponible para recepción.']);
            }
            $sequence = (ProductReception::lockForUpdate()->max('id') ?? 0) + 1;
            $reception = ProductReception::create([
                'purchase_order_id' => $lockedOrder->id,
                'code' => $this->formatCode($sequence),
                'received_at' => $data['received_at'],
                'supplier' => $lockedOrder->supplier?->name,
                'guide_number' => $data['guide_number'] ?? null,
                'guide_file' => $storedFiles['guide_file'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_file' => $storedFiles['invoice_file'] ?? null,
                'order_number' => $lockedOrder->code,
                'order_file' => $storedFiles['order_file'] ?? null,
                'warehouse' => $lockedOrder->destination_warehouse,
                'notes' => $data['notes'] ?? null,
                'received_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $index => $item) {
                $orderItem = PurchaseOrderItem::whereKey($item['purchase_order_item_id'])
                    ->where('purchase_order_id', $lockedOrder->id)->lockForUpdate()->first();
                if (! $orderItem) {
                    throw ValidationException::withMessages(["items.$index.purchase_order_item_id" => 'El producto no pertenece a la orden seleccionada.']);
                }
                $pending = (float) $orderItem->quantity - (float) $orderItem->received_quantity;
                if ($pending <= 0 || (float) $item['quantity'] > $pending) {
                    throw ValidationException::withMessages(["items.$index.quantity" => "La cantidad supera el saldo pendiente de {$orderItem->product_name}."]);
                }
                $product = Product::whereKey($orderItem->product_id)->lockForUpdate()->firstOrFail();
                if (! $product->is_active || $product->type !== 'Producto') {
                    throw ValidationException::withMessages([
                        "items.$index.purchase_order_item_id" => 'El producto seleccionado no está disponible para recepción.',
                    ]);
                }

                $stockBefore = (int) $product->stock;
                $stockAfter = $stockBefore + (int) $item['quantity'];
                $product->update(['stock' => $stockAfter]);
                $orderItem->increment('received_quantity', $item['quantity']);
                $receptionItem=$reception->items()->create([
                    'purchase_order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'quantity' => $item['quantity'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                ]);
                $inventoryStock=InventoryStock::firstOrCreate(['product_id'=>$product->id,'warehouse_id'=>$warehouse->id],['quantity'=>0]);
                $inventoryStock->increment('quantity',$item['quantity']);
                InventoryMovement::create(['code'=>'REC-'.str_pad((string)$receptionItem->id,6,'0',STR_PAD_LEFT),'occurred_at'=>$data['received_at'].' 12:00:00','type'=>'Entrada','product_id'=>$product->id,'destination_warehouse_id'=>$warehouse->id,'quantity'=>$item['quantity'],'reference'=>$reception->code,'product_reception_item_id'=>$receptionItem->id,'created_by'=>auth()->id()]);
            }
            $this->synchronizeReceiptStatus($lockedOrder);
            });
        } catch (\Throwable $exception) {
            foreach ($storedFiles as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('product-receptions.index')
            ->with('success', 'Recepción registrada y stock actualizado correctamente.');
    }

    private function formatCode(int $sequence): string
    {
        return 'GRC 001 - '.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    public function analyzeDocument(Request $request, OpenAiDocumentReader $reader)
    {
        $this->allowed();
        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        return response()->json($reader->analyze($data['document']));
    }

    public function destroy(ProductReception $productReception)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        $files = array_filter([$productReception->guide_file, $productReception->invoice_file, $productReception->order_file]);

        $deleted = DB::transaction(function () use ($productReception) {
            $reception = ProductReception::with('items')->lockForUpdate()->findOrFail($productReception->id);
            $order = $reception->purchase_order_id ? PurchaseOrder::whereKey($reception->purchase_order_id)->lockForUpdate()->first() : null;
            $products = [];
            foreach ($reception->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                $movement=InventoryMovement::where('product_reception_item_id',$item->id)->first();
                $stock=$movement?->destination_warehouse_id ? InventoryStock::where('product_id',$item->product_id)->where('warehouse_id',$movement->destination_warehouse_id)->lockForUpdate()->first() : null;
                $hasLaterMovement=$movement && InventoryMovement::where('product_id',$item->product_id)->where('id','>',$movement->id)->exists();
                $legacyStockChanged=!$movement && (float)$product?->stock !== (float)$item->stock_after;
                if (! $product || $legacyStockChanged || $hasLaterMovement || (float)$product->stock < (float)$item->quantity || ($movement && (!$stock || (float)$stock->quantity < (float)$item->quantity))) {
                    return false;
                }
                $orderItem = $item->purchase_order_item_id ? PurchaseOrderItem::whereKey($item->purchase_order_item_id)->lockForUpdate()->first() : null;
                $products[] = [$product, $stock, $movement, $orderItem, (float)$item->quantity];
            }
            foreach ($products as [$product,$stock,$movement,$orderItem,$quantity]) {
                $product->decrement('stock',$quantity);
                if($stock)$stock->decrement('quantity',$quantity);
                if($orderItem)$orderItem->decrement('received_quantity',$quantity);
                $movement?->delete();
            }
            $reception->delete();
            if($order)$this->synchronizeReceiptStatus($order);

            return true;
        });

        if (! $deleted) {
            return back()->withErrors('No se puede eliminar la recepción porque sus productos ya tienen movimientos de stock posteriores.');
        }
        foreach ($files as $file) Storage::disk('public')->delete($file);

        return back()->with('success', 'Recepción eliminada y stock revertido correctamente.');
    }

    private function synchronizeReceiptStatus(PurchaseOrder $order): void
    {
        $items = $order->items()->whereNotNull('product_id')
            ->whereHas('product', fn ($product) => $product->where('type', 'Producto'))
            ->get(['quantity','received_quantity']);
        $received = $items->sum(fn ($item) => (float) $item->received_quantity);
        $complete = $items->isNotEmpty() && $items->every(fn ($item) => (float) $item->received_quantity >= (float) $item->quantity);
        $order->update(['receipt_status' => $complete ? 'Completa' : ($received > 0 ? 'Parcial' : 'Pendiente')]);
    }
}
