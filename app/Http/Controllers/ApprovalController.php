<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Models\RequirementItem;
use App\Models\PurchaseOrder;
use App\Models\QuotationProcess;
use App\Services\RequirementPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class ApprovalController extends Controller
{
    private const STATUSES = ['Pendiente', 'Visto Bueno', 'Aprobado', 'Aprobado parcial', 'Anulado'];
    private const BULK_STATUSES = ['Pendiente', 'Visto Bueno', 'Aprobado', 'Anulado'];

    private function allowed(): void
    {
        $user = auth()->user();
        abort_unless($user->canAccess('warehouse.approvals') || $user->canReviewRequirements() || $user->canApproveRequirements(), 403);
    }

    private function canReview(): void
    {
        abort_unless(auth()->user()->canReviewRequirements(), 403, 'No tienes autorización para dar Visto Bueno.');
    }

    private function canApprove(): void
    {
        abort_unless(auth()->user()->canApproveRequirements(), 403, 'No tienes autorización para dar la aprobación final.');
    }

    public function index(Request $request)
    {
        $this->allowed();
        $approvalSection = in_array($request->string('seccion')->toString(),['ordenes','cotizaciones'],true) ? $request->string('seccion')->toString() : 'requerimientos';
        $activeStatus = in_array($request->string('estado')->toString(), array_merge(['Todos'], self::STATUSES), true)
            ? $request->string('estado')->toString()
            : 'Todos';
        $counts = RequirementItem::query()
            ->selectRaw('approval_status, count(*) as total')
            ->groupBy('approval_status')
            ->pluck('total', 'approval_status');
        $totalRequirements = Requirement::whereHas('items')->count();
        $items = null;
        if ($activeStatus === 'Todos') {
            $requirements = Requirement::with(['items.decisionMaker', 'items.reviewer'])
                ->whereHas('items')
                ->latest('requested_at')
                ->latest('id')
                ->paginate(12)
                ->withQueryString();
        } else {
            $items = RequirementItem::with(['requirement.decisionMaker', 'requirement.reviewer', 'requirement.items.decisionMaker', 'requirement.items.reviewer', 'decisionMaker', 'reviewer'])
                ->where('approval_status', $activeStatus)
                ->latest('updated_at')
                ->latest('id')
                ->paginate(12)
                ->withQueryString();
            $requirements = $items->getCollection()->pluck('requirement')->filter()->unique('id');
        }

        $purchaseOrders = $approvalSection === 'ordenes'
            ? PurchaseOrder::with(['supplier', 'creator', 'items', 'quotations', 'bankAccount.bank'])->latest()->paginate(12, ['*'], 'ordenes_page')->withQueryString()
            : collect();
        $quotationProcesses = $approvalSection === 'cotizaciones' ? QuotationProcess::with(['requirement.items','quotations.supplier','winner.supplier','submitter'])->where('status','Pendiente aprobación')->latest('submitted_at')->paginate(12,['*'],'cotizaciones_page')->withQueryString() : collect();

        $canReview = auth()->user()->canReviewRequirements();
        $canApprove = auth()->user()->canApproveRequirements();

        return view('approvals.index', compact('items', 'requirements', 'activeStatus', 'counts', 'totalRequirements', 'approvalSection', 'purchaseOrders','quotationProcesses', 'canReview', 'canApprove'));
    }

    public function decideItem(Request $request, RequirementItem $requirementItem)
    {
        $this->allowed();
        $decision = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'approved_quantity' => ['nullable', 'numeric', 'min:0.01'],
        ]);
        $status = $decision['status'];
        if ($status === 'Visto Bueno') {
            $this->canReview();
            if ($requirementItem->approval_status !== 'Pendiente') {
                return back()->withErrors(['status' => 'Solo los ítems pendientes pueden recibir Visto Bueno.']);
            }
        } elseif ($status === 'Pendiente') {
            abort_unless(auth()->user()->isAdministrator(), 403);
        } else {
            $this->canApprove();
            if (in_array($status, ['Aprobado', 'Aprobado parcial'], true) && $requirementItem->approval_status !== 'Visto Bueno') {
                return back()->withErrors(['status' => 'El ítem debe contar primero con Visto Bueno antes de aprobarse.']);
            }
        }
        $approvedQuantity = match ($status) {
            'Aprobado' => (float) $requirementItem->quantity,
            'Aprobado parcial' => (float) ($decision['approved_quantity'] ?? 0),
            default => null,
        };
        if ($status === 'Aprobado parcial' && $approvedQuantity >= (float) $requirementItem->quantity) {
            return back()->withErrors(['approved_quantity' => 'La cantidad para aprobación parcial debe ser menor que la cantidad requerida.']);
        }

        DB::transaction(function () use ($requirementItem, $status, $approvedQuantity): void {
            $changes = ['approval_status' => $status];
            if ($status === 'Visto Bueno') {
                $changes += ['reviewed_at' => now(), 'reviewed_by' => auth()->id(), 'approved_quantity' => null, 'decision_at' => null, 'decision_by' => null];
            } elseif ($status === 'Pendiente') {
                $changes += ['reviewed_at' => null, 'reviewed_by' => null, 'approved_quantity' => null, 'decision_at' => null, 'decision_by' => null];
            } else {
                $changes += ['approved_quantity' => $approvedQuantity, 'decision_at' => now(), 'decision_by' => auth()->id()];
            }
            $requirementItem->update($changes);
            $this->synchronizeRequirement($requirementItem->requirement()->firstOrFail());
        });

        return redirect()->route('approvals.index', ['estado' => $status])
            ->with('success', "Ítem marcado como {$status}.");
    }

    public function decide(Request $request, Requirement $requirement)
    {
        $this->allowed();
        $decision = $request->validate([
            'status' => ['required', Rule::in(self::BULK_STATUSES)],
        ])['status'];

        if ($decision === 'Visto Bueno') {
            $this->canReview();
            if ($requirement->items()->where('approval_status', '!=', 'Pendiente')->exists()) {
                return back()->withErrors(['status' => 'El Visto Bueno total solo se puede aplicar cuando todos los ítems están pendientes.']);
            }
        } elseif ($decision === 'Pendiente') {
            abort_unless(auth()->user()->isAdministrator(), 403);
        } else {
            $this->canApprove();
            if ($decision === 'Aprobado' && $requirement->items()->where('approval_status', '!=', 'Visto Bueno')->exists()) {
                return back()->withErrors(['status' => 'Todos los ítems deben tener Visto Bueno antes de la aprobación total.']);
            }
        }

        DB::transaction(function () use ($requirement, $decision): void {
            $changes = ['approval_status' => $decision];
            if ($decision === 'Visto Bueno') {
                $changes += ['reviewed_at' => now(), 'reviewed_by' => auth()->id(), 'approved_quantity' => null, 'decision_at' => null, 'decision_by' => null];
            } elseif ($decision === 'Pendiente') {
                $changes += ['reviewed_at' => null, 'reviewed_by' => null, 'approved_quantity' => null, 'decision_at' => null, 'decision_by' => null];
            } else {
                $changes += ['approved_quantity' => $decision === 'Aprobado' ? DB::raw('quantity') : null, 'decision_at' => now(), 'decision_by' => auth()->id()];
            }
            $requirement->items()->update($changes);
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
            $requirement->items()->update(['approval_status'=>'Pendiente', 'reviewed_at'=>null, 'reviewed_by'=>null, 'approved_quantity'=>null, 'decision_at'=>null, 'decision_by'=>null]);
            $requirement->update(['status'=>'Pendiente', 'reviewed_at'=>null, 'reviewed_by'=>null, 'decision_at'=>null, 'decision_by'=>null]);
        });

        return back()->with('success', 'Las decisiones fueron eliminadas y todos los ítems volvieron a Pendiente.');
    }

    public function decidePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->allowed();
        $this->canApprove();
        $status = $request->validate(['status' => ['required', Rule::in(['Aprobada', 'Anulada'])]])['status'];
        $purchaseOrder->update(['status' => $status]);
        return redirect()->route('approvals.index', ['seccion' => 'ordenes'])->with('success', "Orden {$purchaseOrder->code} marcada como {$status}.");
    }

    public function decideQuotation(Request $request, QuotationProcess $quotationProcess)
    {
        $this->allowed();
        $this->canApprove();
        $data=$request->validate(['status'=>['required',Rule::in(['Aprobada','Rechazada'])],'observation'=>['nullable','string','max:2000','required_if:status,Rechazada']]);
        abort_unless($quotationProcess->status==='Pendiente aprobación',422);
        $quotationProcess->update(['status'=>$data['status'],'approval_observation'=>$data['observation']??null,'decided_at'=>now(),'decided_by'=>auth()->id()]);
        return redirect()->route('approvals.index',['seccion'=>'cotizaciones'])->with('success',$data['status']==='Aprobada'?'Cotización ganadora aprobada y enviada a órdenes de compra.':'Propuesta rechazada y devuelta a Cotizaciones con la observación.');
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

    public function itemImage(RequirementItem $requirementItem)
    {
        $this->allowed();
        abort_unless($requirementItem->image_path && Storage::disk('local')->exists($requirementItem->image_path), 404);

        return response()->file(Storage::disk('local')->path($requirementItem->image_path), [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function synchronizeRequirement(Requirement $requirement): void
    {
        $items = $requirement->items()->get();
        $statuses = $items->pluck('approval_status');
        $status = $statuses->isEmpty() || $statuses->every(fn ($itemStatus) => $itemStatus === 'Pendiente')
            ? 'Pendiente'
            : ($statuses->contains('Visto Bueno') ? 'Visto Bueno' : 'Aprobación');
        $reviewed = $items->filter(fn ($item) => $item->reviewed_at)->sortByDesc('reviewed_at')->first();
        $decided = $items->filter(fn ($item) => $item->decision_at)->sortByDesc('decision_at')->first();

        $requirement->update([
            'status' => $status,
            'reviewed_at' => $reviewed?->reviewed_at,
            'reviewed_by' => $reviewed?->reviewed_by,
            'decision_at' => $decided?->decision_at,
            'decision_by' => $decided?->decision_by,
        ]);
    }
}
