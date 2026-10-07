<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->session()->get('active_company_id');
        $company = $companyId ? Company::where('is_active', true)->find($companyId) : null;

        if (! $company) {
            $company = Company::where('is_active', true)->orderBy('id')->first();
            if ($company) {
                $request->session()->put('active_company_id', $company->id);
            }
        }

        view()->share('activeCompany', $company);
        view()->share('availableCompanies', Company::where('is_active', true)->orderBy('legal_name')->get());

        return $next($request);
    }
}
