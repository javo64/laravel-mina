<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Services\RequirementPdfGenerator;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    private function allowed(): void { abort_unless(auth()->user()->canAccess('approvals'), 403); }
    public function index(Request $request) { $this->allowed(); $requirements = Requirement::with(['items','decisionMaker'])->latest('requested_at')->paginate(10); return view('approvals.index', compact('requirements')); }
    public function decide(Request $request, Requirement $requirement) { $this->allowed(); $decision = $request->validate(['status'=>'required|in:Aprobado,Rechazado'])['status']; $requirement->update(['status'=>$decision,'decision_at'=>now(),'decision_by'=>auth()->id()]); return back()->with('success',"Requerimiento {$decision} correctamente."); }

    public function destroy(Requirement $requirement)
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        if ($requirement->status === 'Pendiente' || ! $requirement->decision_at) {
            return back()->withErrors('Este requerimiento todavía no tiene una aprobación para eliminar.');
        }
        $requirement->update(['status'=>'Pendiente','decision_at'=>null,'decision_by'=>null]);

        return back()->with('success', 'Aprobación eliminada; el requerimiento volvió al estado Pendiente.');
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
}
