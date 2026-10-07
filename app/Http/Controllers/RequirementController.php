<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Responsible;
use App\Models\CostCenter;
use App\Models\ProductGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RequirementController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('warehouse.requirements'), 403); }

    public function index(Request $request)
    {
        $this->allowed();
        $requirements = Requirement::with('items')
            ->when($request->q, fn ($query, $value) => $query->where(fn ($search) => $search->where('code','like',"%$value%")->orWhere('responsible','like',"%$value%")->orWhere('project','like',"%$value%")))
            ->latest('requested_at')->paginate(10);
        $products = Product::where('is_active', true)->orderBy('name')->get();
        $responsibles = Responsible::where('is_active', true)->orderBy('name')->get();
        $areas = Area::where('is_active', true)->orderBy('name')->get();
        $projects = Project::where('is_active', true)->orderBy('name')->get();
        $categories = ProductCategory::where('is_active', true)->orderBy('name')->get();
        $units = MeasurementUnit::where('is_active', true)->orderBy('name')->get();
        $costCenters = CostCenter::where('is_active', true)->whereNotNull('parent_id')->with('parent')->orderBy('name')->get();
        $groups = ProductGroup::with(['subgroups' => fn ($query) => $query->where('is_active', true)])->where('is_active', true)->orderBy('code')->get();
        $groupData = $groups->map(fn ($group) => [
            'id' => $group->id,
            'subgroups' => $group->subgroups->map(fn ($subgroup) => ['id' => $subgroup->id, 'code' => $subgroup->code, 'name' => $subgroup->name])->values()->all(),
        ])->values()->all();

        return view('requirements.index', compact('requirements','products','responsibles','areas','projects','categories','units','costCenters','groups','groupData'));
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate([
            'requested_at' => ['required','date'],
            'responsible' => ['required','max:255','exists:responsibles,name'],
            'project' => ['required','max:255','exists:projects,name'],
            'area' => ['required','max:255','exists:areas,name'],
            'items' => ['required','array','min:1'],
            'items.*.product_id' => ['required','distinct','exists:products,id'],
            'items.*.description' => ['nullable','max:500'],
            'items.*.quantity' => ['required','numeric','min:0.01'],
            'items.*.priority' => ['required','in:Alta,Media,Baja'],
            'items.*.cost_center_id' => ['required','exists:cost_centers,id'],
            'items.*.image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
        ]);

        $storedImages = [];
        try {
            DB::transaction(function () use ($data, $request, &$storedImages) {
                $sequence = (Requirement::max('id') ?? 0) + 1;
                $weight = ['Baja' => 1, 'Media' => 2, 'Alta' => 3];
                $generalPriority = collect($data['items'])->sortByDesc(fn ($item) => $weight[$item['priority']])->first()['priority'];
                $requirement = Requirement::create([
                    'code' => 'REQ-'.now()->year.'-'.str_pad((string)$sequence, 4, '0', STR_PAD_LEFT),
                    'requested_at' => $data['requested_at'], 'responsible' => $data['responsible'],
                    'project' => $data['project'], 'area' => $data['area'],
                    'priority' => $generalPriority, 'status' => 'Pendiente',
                ]);

                foreach ($data['items'] as $index => $item) {
                    $product = Product::where('is_active', true)->findOrFail($item['product_id']);
                    $costCenter = CostCenter::where('is_active', true)->whereNotNull('parent_id')->findOrFail($item['cost_center_id']);
                    $imagePath = $request->file("items.{$index}.image")?->store('requirement-items', 'local');
                    if ($imagePath) $storedImages[] = $imagePath;
                    $requirement->items()->create([
                        'product_id' => $product->id, 'product_name' => $product->name,
                        'category' => $product->category, 'unit' => $product->unit,
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'], 'priority' => $item['priority'],
                        'cost_center_id' => $costCenter->id, 'cost_center' => $costCenter->name,
                        'image_path' => $imagePath,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedImages);
            throw $exception;
        }

        return back()->with('success', 'Requerimiento guardado como pendiente.');
    }

    public function destroy(Requirement $requirement)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if ($requirement->items()->whereHas('purchaseOrderItems')->exists()) {
            return back()->withErrors('Primero debes eliminar las órdenes vinculadas a este requerimiento.');
        }
        $images = $requirement->items()->whereNotNull('image_path')->pluck('image_path')->all();
        $quotes = $requirement->quotationProcess?->quotations()->pluck('path')->all() ?? [];
        $requirement->delete();
        Storage::disk('local')->delete([...$images, ...$quotes]);

        return back()->with('success', 'Requerimiento eliminado correctamente.');
    }

    public function update(Request $request, Requirement $requirement)
    {
        $this->allowed();
        if ($requirement->status !== 'Pendiente') {
            return back()->withErrors('Solo se pueden editar requerimientos que estén pendientes.');
        }

        $data = $request->validate([
            'requested_at' => ['required', 'date'],
            'responsible' => ['required', 'max:255', 'exists:responsibles,name'],
            'project' => ['required', 'max:255', 'exists:projects,name'],
            'area' => ['required', 'max:255', 'exists:areas,name'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.description' => ['nullable', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.priority' => ['required', 'in:Alta,Media,Baja'],
            'items.*.cost_center_id' => ['required', 'exists:cost_centers,id'],
            'items.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $oldImages = [];
        try {
            DB::transaction(function () use ($data, $request, $requirement, &$oldImages): void {
                $locked = Requirement::with('items')->lockForUpdate()->findOrFail($requirement->id);
                if ($locked->status !== 'Pendiente') throw new \RuntimeException('El requerimiento cambió de estado y ya no se puede editar.');
                $currentItems = $locked->items->keyBy('id');
                if ($currentItems->count() !== count($data['items'])) throw new \RuntimeException('No se puede alterar la estructura del requerimiento una vez guardado.');

                $weight = ['Baja' => 1, 'Media' => 2, 'Alta' => 3];
                $priority = collect($data['items'])->sortByDesc(fn ($item) => $weight[$item['priority']])->first()['priority'];
                $locked->update(['requested_at' => $data['requested_at'], 'responsible' => $data['responsible'], 'project' => $data['project'], 'area' => $data['area'], 'priority' => $priority]);

                foreach ($data['items'] as $index => $item) {
                    $requirementItem = $currentItems->get($item['id']);
                    if (! $requirementItem) throw new \RuntimeException('Uno de los ítems no pertenece al requerimiento.');
                    $product = Product::where('is_active', true)->findOrFail($item['product_id']);
                    $costCenter = CostCenter::where('is_active', true)->whereNotNull('parent_id')->findOrFail($item['cost_center_id']);
                    $imagePath = $requirementItem->image_path;
                    if ($request->hasFile("items.{$index}.image")) {
                        $imagePath = $request->file("items.{$index}.image")->store('requirement-items', 'local');
                        if ($requirementItem->image_path) $oldImages[] = $requirementItem->image_path;
                    }
                    $requirementItem->update(['product_id' => $product->id, 'product_name' => $product->name, 'category' => $product->category, 'unit' => $product->unit, 'description' => $item['description'] ?? null, 'quantity' => $item['quantity'], 'priority' => $item['priority'], 'cost_center_id' => $costCenter->id, 'cost_center' => $costCenter->name, 'image_path' => $imagePath]);
                }
            });
        } catch (Throwable $exception) {
            throw $exception;
        }
        Storage::disk('local')->delete($oldImages);
        return back()->with('success', 'Requerimiento pendiente actualizado correctamente.');
    }
}
