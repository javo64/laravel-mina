<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReception;
use App\Models\BusinessPartner;
use App\Services\OpenAiDocumentReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductReceptionController extends Controller
{
    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('products'), 403);
    }

    public function index(Request $request)
    {
        $this->allowed();
        $receptions = ProductReception::with(['items', 'receiver'])
            ->when($request->q, fn ($query, $value) => $query->where(fn ($search) => $search
                ->where('code', 'like', "%$value%")
                ->orWhere('supplier', 'like', "%$value%")
                ->orWhere('guide_number', 'like', "%$value%")
                ->orWhere('invoice_number', 'like', "%$value%")
                ->orWhere('order_number', 'like', "%$value%")))
            ->latest('received_at')->latest('id')->paginate(10)->withQueryString();
        $products = Product::where('is_active', true)->where('type', 'Producto')->orderBy('name')->get();
        $suppliers = BusinessPartner::where('is_active', true)
            ->whereIn('type', ['Proveedor', 'Cliente y proveedor'])
            ->orderBy('name')->get(['id', 'document_number', 'name', 'trade_name']);
        $nextCode = $this->formatCode((ProductReception::max('id') ?? 0) + 1);

        return view('product-receptions.index', compact('receptions', 'products', 'suppliers', 'nextCode'));
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'received_at' => ['required', 'date'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'guide_number' => ['nullable', 'string', 'max:100'],
            'guide_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'guide_camera' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'invoice_camera' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'order_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'order_camera' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'warehouse' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        if (filled($data['supplier'] ?? null) && ! BusinessPartner::where('name', $data['supplier'])
            ->where('is_active', true)->whereIn('type', ['Proveedor', 'Cliente y proveedor'])->exists()) {
            throw ValidationException::withMessages([
                'supplier' => 'Selecciona un proveedor activo registrado en Logística.',
            ]);
        }

        $storedFiles = [];
        foreach (['guide', 'invoice', 'order'] as $document) {
            $field = $request->hasFile($document.'_camera') ? $document.'_camera' : $document.'_file';
            if ($request->hasFile($field)) {
                $storedFiles[$document.'_file'] = $request->file($field)->store('recepciones', 'public');
            }
        }

        try {
            DB::transaction(function () use ($data, $storedFiles) {
            $sequence = (ProductReception::lockForUpdate()->max('id') ?? 0) + 1;
            $reception = ProductReception::create([
                'code' => $this->formatCode($sequence),
                'received_at' => $data['received_at'],
                'supplier' => $data['supplier'] ?? null,
                'guide_number' => $data['guide_number'] ?? null,
                'guide_file' => $storedFiles['guide_file'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_file' => $storedFiles['invoice_file'] ?? null,
                'order_number' => $data['order_number'] ?? null,
                'order_file' => $storedFiles['order_file'] ?? null,
                'warehouse' => $data['warehouse'],
                'notes' => $data['notes'] ?? null,
                'received_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $index => $item) {
                $product = Product::whereKey($item['product_id'])->lockForUpdate()->firstOrFail();
                if (! $product->is_active || $product->type !== 'Producto') {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" => 'El producto seleccionado no está disponible para recepción.',
                    ]);
                }

                $stockBefore = (int) $product->stock;
                $stockAfter = $stockBefore + (int) $item['quantity'];
                $product->update(['stock' => $stockAfter]);
                $reception->items()->create([
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'quantity' => $item['quantity'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                ]);
            }
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
}
