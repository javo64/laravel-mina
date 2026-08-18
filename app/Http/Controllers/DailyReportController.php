<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\DailyReportForm;
use App\Models\User;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DailyReportController extends Controller
{
    private const TYPES = ['area','integer','decimal','text','yes_no','options','multi_select','dropdown','time','date','qr','barcode','photo','signature','formula','section'];

    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('daily-reports'), 403);
    }

    private function canManage(DailyReportForm $form): void
    {
        $this->allowed();
        abort_unless(auth()->user()->canAccess('users') || $form->created_by === auth()->id(), 403);
    }

    private function canFill(DailyReportForm $form): void
    {
        $this->allowed();
        abort_unless($form->is_active && (
            auth()->user()->canAccess('users') ||
            $form->created_by === auth()->id() ||
            $form->users()->whereKey(auth()->id())->exists()
        ), 403);
    }

    public function index(Request $request)
    {
        $this->allowed();
        $user = auth()->user();
        $forms = DailyReportForm::withCount(['fields','reports'])->with('creator')
            ->when(! $user->canAccess('users'), fn ($query) => $query->where(fn ($q) => $q
                ->where('created_by', $user->id)
                ->orWhereHas('users', fn ($users) => $users->whereKey($user->id))))
            ->when($request->q, fn ($q, $value) => $q->where('name', 'like', "%{$value}%"))
            ->latest()->paginate(12);
        $recentReports = DailyReport::with(['form','user'])
            ->when(! $user->canAccess('users'), fn ($q) => $q->where('user_id', $user->id))
            ->latest('reported_at')->limit(8)->get();

        return view('daily-reports.index', compact('forms', 'recentReports'));
    }

    public function create()
    {
        $this->allowed();
        return view('daily-reports.builder', ['form' => new DailyReportForm(), 'users' => User::where('is_active', true)->orderBy('name')->get(), 'types' => self::TYPES]);
    }

    public function store(Request $request)
    {
        $this->allowed();
        $form = DB::transaction(function () use ($request) {
            $data = $this->validatedForm($request);
            $data['created_by'] = auth()->id();
            $form = DailyReportForm::create($data);
            $this->saveFields($form, $request->input('fields', []));
            $form->users()->sync($request->input('user_ids', []));
            return $form;
        });
        return redirect()->route('daily-reports.edit', $form)->with('success', 'Cartilla digital creada correctamente.');
    }

    public function edit(DailyReportForm $dailyReportForm)
    {
        $this->canManage($dailyReportForm);
        $dailyReportForm->load(['fields','users']);
        return view('daily-reports.builder', ['form' => $dailyReportForm, 'users' => User::where('is_active', true)->orderBy('name')->get(), 'types' => self::TYPES]);
    }

    public function update(Request $request, DailyReportForm $dailyReportForm)
    {
        $this->canManage($dailyReportForm);
        DB::transaction(function () use ($request, $dailyReportForm) {
            $dailyReportForm->update($this->validatedForm($request));
            $dailyReportForm->fields()->delete();
            $this->saveFields($dailyReportForm, $request->input('fields', []));
            $dailyReportForm->users()->sync($request->input('user_ids', []));
        });
        return back()->with('success', 'Configuración de la cartilla actualizada.');
    }

    public function preview(DailyReportForm $dailyReportForm)
    {
        $this->canManage($dailyReportForm);
        $dailyReportForm->load('fields');
        return view('daily-reports.fill', ['form' => $dailyReportForm, 'preview' => true, 'areas' => Area::orderBy('name')->get()]);
    }

    public function fill(DailyReportForm $dailyReportForm)
    {
        $this->canFill($dailyReportForm);
        $dailyReportForm->load('fields');
        return view('daily-reports.fill', ['form' => $dailyReportForm, 'preview' => false, 'areas' => Area::orderBy('name')->get()]);
    }

    public function submit(Request $request, DailyReportForm $dailyReportForm)
    {
        $this->canFill($dailyReportForm);
        $dailyReportForm->load('fields');
        $rules = ['latitude' => [$dailyReportForm->use_gps ? 'required' : 'nullable','numeric'], 'longitude' => [$dailyReportForm->use_gps ? 'required' : 'nullable','numeric']];
        foreach ($dailyReportForm->fields as $field) {
            if ($field->type === 'section') continue;
            $base = $field->is_required ? ['required'] : ['nullable'];
            $fieldRules = match ($field->type) {
                'integer' => [...$base, 'integer'],
                'decimal','formula' => [...$base, 'numeric'],
                'date' => [...$base, 'date'],
                'photo' => [...$base, 'file', 'image', 'max:10240'],
                'multi_select' => [...$base, 'array', ...($field->is_required ? ['min:1'] : [])],
                'text' => [...$base, 'string', 'min:'.($field->settings['min_chars'] ?? 0), 'max:'.($field->settings['max_chars'] ?? 500)],
                default => [...$base, 'string', 'max:5000'],
            };
            if (in_array($field->type, ['integer','decimal'], true) && ($field->settings['has_limits'] ?? false)) {
                if (($field->settings['min'] ?? '') !== '') $fieldRules[] = 'min:'.$field->settings['min'];
                if (($field->settings['max'] ?? '') !== '') $fieldRules[] = 'max:'.$field->settings['max'];
            }
            $rules["responses.{$field->field_key}"] = $fieldRules;
        }
        $request->validate($rules, ['latitude.required' => 'Debe autorizar el GPS antes de guardar el parte.']);
        $responses = [];
        foreach ($dailyReportForm->fields as $field) {
            if ($field->type === 'section') continue;
            $value = $request->input("responses.{$field->field_key}");
            if ($field->type === 'photo' && $request->hasFile("responses.{$field->field_key}")) {
                $value = $request->file("responses.{$field->field_key}")->store('daily-reports', 'public');
            }
            $responses[$field->field_key] = $value;
        }
        DailyReport::create(['daily_report_form_id'=>$dailyReportForm->id,'user_id'=>auth()->id(),'reported_at'=>now(),'latitude'=>$request->latitude,'longitude'=>$request->longitude,'responses'=>$responses]);
        return redirect()->route('daily-reports.index')->with('success', 'Parte diario registrado correctamente.');
    }

    private function validatedForm(Request $request): array
    {
        $data = $request->validate([
            'name'=>'required|string|max:150','description'=>'nullable|string|max:1000','scope'=>'nullable|string|max:150',
            'fields'=>'required|array|min:1','fields.*.name'=>'required|string|max:150','fields.*.type'=>['required', Rule::in(self::TYPES)],
            'fields.*.section'=>'nullable|string|max:100','fields.*.field_key'=>'nullable|string|max:80','fields.*.help_text'=>'nullable|string|max:500',
            'fields.*.options'=>'nullable|string','fields.*.formula'=>'nullable|string|max:500','fields.*.settings'=>'nullable|string','user_ids'=>'nullable|array','user_ids.*'=>'exists:users,id',
        ]);
        foreach (['is_active','use_gps','evaluator_location','allow_export','exact_search','allow_update','auto_collapse'] as $option) $data[$option] = $request->boolean($option);
        unset($data['fields'], $data['user_ids']);
        return $data;
    }

    private function saveFields(DailyReportForm $form, array $fields): void
    {
        $used = [];
        foreach ($fields as $position => $field) {
            $base = Str::slug($field['field_key'] ?? $field['name'], '_') ?: 'campo';
            $key = $base; $suffix = 2;
            while (in_array($key, $used, true)) $key = $base.'_'.($suffix++);
            $used[] = $key;
            $form->fields()->create([
                'field_key'=>$key,'section'=>$field['section'] ?: 'Datos generales','name'=>$field['name'],'type'=>$field['type'],
                'help_text'=>$field['help_text'] ?? null,'options'=>array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $field['options'] ?? '')))),
                'formula'=>$field['formula'] ?? null,'settings'=>json_decode($field['settings'] ?? '{}', true) ?: [],'is_required'=>filter_var($field['is_required'] ?? false, FILTER_VALIDATE_BOOL),
                'copy_previous'=>filter_var($field['copy_previous'] ?? false, FILTER_VALIDATE_BOOL),'position'=>$position,
            ]);
        }
    }
}
