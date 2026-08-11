<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Responsible;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequirementController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('requirements'), 403); }

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

        return view('requirements.index', compact('requirements','products','responsibles','areas','projects','categories','units'));
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
        ]);

        DB::transaction(function () use ($data) {
            $sequence = (Requirement::max('id') ?? 0) + 1;
            $weight = ['Baja' => 1, 'Media' => 2, 'Alta' => 3];
            $generalPriority = collect($data['items'])->sortByDesc(fn ($item) => $weight[$item['priority']])->first()['priority'];
            $requirement = Requirement::create([
                'code' => 'REQ-'.now()->year.'-'.str_pad((string)$sequence, 4, '0', STR_PAD_LEFT),
                'requested_at' => $data['requested_at'], 'responsible' => $data['responsible'],
                'project' => $data['project'], 'area' => $data['area'],
                'priority' => $generalPriority, 'status' => 'Pendiente',
            ]);

            foreach ($data['items'] as $item) {
                $product = Product::where('is_active', true)->findOrFail($item['product_id']);
                $requirement->items()->create([
                    'product_id' => $product->id, 'product_name' => $product->name,
                    'category' => $product->category, 'unit' => $product->unit,
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'], 'priority' => $item['priority'],
                ]);
            }
        });

        return back()->with('success', 'Requerimiento guardado como pendiente.');
    }
}
