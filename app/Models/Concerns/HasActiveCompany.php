<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\ActiveCompany;
use Illuminate\Database\Eloquent\Builder;

trait HasActiveCompany
{
    public static function bootHasActiveCompany(): void
    {
        static::addGlobalScope('active_company', function (Builder $builder): void {
            if ($companyId = ActiveCompany::id()) {
                $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
            }
        });

        static::creating(function ($model): void {
            // También cubre importaciones, seeders y tareas sin sesión web.
            // Ningún documento operativo debe quedar fuera de una empresa.
            $companyId = ActiveCompany::id() ?: Company::where('is_active', true)->orderBy('id')->value('id');
            if (! $model->company_id && $companyId) {
                $model->company_id = $companyId;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
