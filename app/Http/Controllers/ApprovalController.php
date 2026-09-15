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
    private const STATUSES = ['Pendiente', 'Aprobado', 'Aprobado parcial', 'Anulado'];
    private const BULK_STATUSES = ['Pendiente', 'Aprobado', 'Anulado'];

    private function allowed(): void
    {
        abort_unless(auth()->user()->canAccess('warehouse.approvals'), 403);
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
            $requirements = Requirement::with(['items.decisionMaker'])
                ->whereHas('items')
                ->latest('requested_at')
                ->latest('id')
                ->paginate(12)
                ->withQueryString();
        } else {
            $items = RequirementItem::with(['requirement.decisionMaker', 'requirement.items.decisionMaker', 'decisionMaker'])
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

        return view('approvals.index', compact('items', 'requirements', 'activeStatus', 'counts', 'totalRequirements', 'approvalSection', 'purchaseOrders','quotationProcesses'));
    }

    public function decideItem(Request $request, RequirementItem $requirementItem)
    {
        $this->allowed();
        $decision = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'approved_quantity' => ['nullable', 'numeric', 'min:0.01'],
        ]);
        $status = $decision['status'];
        $approvedQuantity = match ($status) {
            'Aprobado' => (float) $requirementItem->quantity,
            'Aprobado parcial' => (float) ($decision['approved_quantity'] ?? 0),
            default => null,
        };
        if ($status === 'Aprobado parcial' && $approvedQuantity >= (float) $requirementItem->quantity) {
            return back()->withErrors(['approved_quantity' => 'La cantidad para aprobación parcial debe ser menor que la cantidad requerida.']);
        }

        DB::transaction(function () use ($requirementItem, $status, $approvedQuantity): void {
            $requirementItem->update([
                'approval_status' => $status,
                'approved_quantity' => $approvedQuantity,
                'decision_at' => $status === 'Pendiente' ? null : now(),
                'decision_by' => $status === 'Pendiente' ? null : auth()->id(),
            ]);
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

        DB::transaction(function () use ($requirement, $decision): void {
            $requirement->items()->update([
                'approval_status' => $decision,
                'approved_quantity' => $decision === 'Aprobado' ? DB::raw('quantity') : null,
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
            $requirement->items()->update(['approval_status'=>'Pendiente', 'approved_quantity'=>null, 'decision_at'=>null, 'decision_by'=>null]);
            $requirement->update(['status'=>'Pendiente', 'decision_at'=>null, 'decision_by'=>null]);
        });

        return back()->with('success', 'Las decisiones fueron eliminadas y todos los ítems volvieron a Pendiente.');
    }

    public function decidePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->allowed();
        $status = $request->validate(['status' => ['required', Rule::in(['Aprobada', 'Anulada'])]])['status'];
        $purchaseOrder->update(['status' => $status]);
        return redirect()->route('approvals.index', ['seccion' => 'ordenes'])->with('success', "Orden {$purchaseOrder->code} marcada como {$status}.");
    }

    public function decideQuotation(Request $request, QuotationProcess $quotationProcess)
    {
        $this->allowed();
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
        $statuses = $requirement->items()->pluck('approval_status');
        $status = $statuses->every(fn ($itemStatus) => $itemStatus === 'Pendiente')
            ? 'Pendiente'
            : ($statuses->isNotEmpty() && $statuses->every(fn ($itemStatus) => $itemStatus === 'Aprobado') ? 'Aprobado total' : 'Aprobado parcial');
        $isPending = $status === 'Pendiente';

        $requirement->update([
            'status' => $status,
            'decision_at' => $isPending ? null : now(),
            'decision_by' => $isPending ? null : auth()->id(),
        ]);
    }
}
