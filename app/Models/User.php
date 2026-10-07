<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Support\PermissionCatalog;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'branch', 'profile', 'is_active', 'permissions', 'last_access_at'];
    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'last_access_at' => 'datetime',
        ];
    }

    public function canAccess(string $module): bool
    {
        if ($this->isAdministrator()) return true;
        $stored=$this->permissions??[];
        if(isset(PermissionCatalog::LEGACY[$module])) return collect($stored)->contains(fn($permission)=>$permission===$module || in_array($permission,PermissionCatalog::LEGACY[$module],true));
        return collect($stored)->contains(fn($permission)=>PermissionCatalog::grants($permission,$module));
    }

    public function landingRoute(): string
    {
        $routes=['warehouse.products'=>'products.index','warehouse.receptions'=>'product-receptions.index','warehouse.inventory'=>'inventory.index','warehouse.structure'=>'branches.index','warehouse.requirements'=>'requirements.index','warehouse.approvals'=>'approvals.index','logistics.partners'=>'business-partners.index','logistics.quotations'=>'quotations.index','logistics.purchase-orders'=>'purchase-orders.index','costs.cost-centers'=>'cost-centers.index','daily-reports.forms'=>'daily-reports.index','administration.users'=>'users.index','administration.openai'=>'settings.openai.edit','administration.document-api'=>'settings.document-api.edit'];
        foreach($routes as $permission=>$route) if($this->canAccess($permission)) return $route;
        return 'login';
    }

    public function isAdministrator(): bool
    {
        return $this->profile === 'Administrador';
    }

    public function canReviewRequirements(): bool
    {
        if ($this->isAdministrator()) return true;

        $permissions = $this->permissions ?? [];
        return in_array('warehouse.approvals.review', $permissions, true)
            || in_array('warehouse.approvals.approve', $permissions, true)
            || in_array('warehouse.approvals', $permissions, true)
            || in_array('approvals', $permissions, true);
    }

    public function canApproveRequirements(): bool
    {
        if ($this->isAdministrator()) return true;

        $permissions = $this->permissions ?? [];
        return in_array('warehouse.approvals.approve', $permissions, true)
            || in_array('warehouse.approvals', $permissions, true)
            || in_array('approvals', $permissions, true);
    }

    public function dailyReports()
    {
        return $this->hasMany(DailyReport::class);
    }
}
