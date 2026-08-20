<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Models\RequirementItem;
use App\Services\RequirementPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApprovalController extends Controller
{
    private const STATUSES = ['Pendiente', 'Aprobado', 'Anulado'];

    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('approvals'), 403);
    }

    public function index(Request $request)
    {
        $this->allowed();
        $activeStatus = in_array($request->string('estado')->toString(), array_merge(['Todos'], self::STATUSES), true)
            ? $request->string('estado')->toString()
            : 'Todos';
        $counts = RequirementItem::query()
            ->selectRaw('approval_status, count(*) as total')
            ->groupBy('approval_status')
            ->pluck('total', 'approval_status');
        $items = RequirementItem::with(['requirement.decisionMaker', 'requirement.items.decisionMaker', 'decisionMaker'])
            ->when($activeStatus !== 'Todos', fn ($query) => $query->where('approval_status', $activeStatus))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();
        $requirements = $items->getCollection()->pluck('requirement')->filter()->unique('id');

        return view('approvals.index', compact('items', 'requirements', 'activeStatus', 'counts'));
    }

    public function decideItem(Request $request, RequirementItem $requirementItem)
    {
        $this->allowed();
        $decision = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ])['status'];

        DB::transaction(function () use ($requirementItem, $decision): void {
            $requirementItem->update([
                'approval_status' => $decision,
                'decision_at' => $decision === 'Pendiente' ? null : now(),
                'decision_by' => $decision === 'Pendiente' ? null : auth()->id(),
            ]);
            $this->synchronizeRequirement($requirementItem->requirement()->firstOrFail());
        });

        return redirect()->route('approvals.index', ['estado' => $decision])
            ->with('success', "Ítem marcado como {$decision}.");
    }

    public function decide(Request $request, Requirement $requirement)
    {
        $this->allowed();
        $decision = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ])['status'];

        DB::transaction(function () use ($requirement, $decision): void {
            $requirement->items()->update([
                'approval_status' => $decision,
                'decision_at' => $decision === 'Pendiente' ? null : now(),
                'decision_by' => $decision === 'Pendiente' ? null : auth()->id(),
            ]);
            $this->synchronizeRequirement($requirement);
        });

        return back()->with('success', "Todos los ítems del requerimiento quedaron como {$decision}.");
    }

    public function destroy(Requirement $requirement)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if ($requirement->status === 'Pendiente' || ! $requirement->decision_at) {
            return back()->withErrors('Este requerimiento todavía no tiene una aprobación para eliminar.');
        }

        DB::transaction(function () use ($requirement): void {
            $requirement->items()->update(['approval_status'=>'Pendiente', 'decision_at'=>null, 'decision_by'=>null]);
            $requirement->update(['status'=>'Pendiente', 'decision_at'=>null, 'decision_by'=>null]);
        });

        return back()->with('success', 'Las decisiones fueron eliminadas y todos los ítems volvieron a Pendiente.');
    }

    public function pdf(Request $request, Requirement $requirement, RequirementPdfGenerator $generator)
    {
        $this->allowed();
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        $filename = 'requerimiento-'.$requirement->code.'.pdf';

        return response($generator->render($requirement), 200, [
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>$disposition.'; filename="'.$filename.'"',
            'Cache-Control'=>'private, max-age=60',
        ]);
    }

    private function synchronizeRequirement(Requirement $requirement): void
    {
        $statuses = $requirement->items()->pluck('approval_status')->unique()->values();
        $status = $statuses->count() === 1 ? ($statuses->first() ?: 'Pendiente') : 'Parcial';
        $isPending = $status === 'Pendiente';

        $requirement->update([
            'status' => $status,
            'decision_at' => $isPending ? null : now(),
            'decision_by' => $isPending ? null : auth()->id(),
        ]);
    }
}
