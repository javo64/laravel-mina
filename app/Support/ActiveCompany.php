<?php

namespace App\Support;

use App\Models\Company;

class ActiveCompany
{
    public static function id(): ?int
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();
        return $request->hasSession() ? $request->session()->get('active_company_id') : null;
    }

    public static function current(): ?Company
    {
        $id = self::id();
        return $id ? Company::where('is_active', true)->find($id) : null;
    }
}
