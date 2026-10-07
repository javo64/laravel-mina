<?php

namespace App\Models;

use App\Models\Concerns\HasActiveCompany;
use Illuminate\Database\Eloquent\Model;

class ProductSubgroup extends Model
{
    use HasActiveCompany;

    protected $fillable = ['company_id', 'product_group_id', 'code', 'name', 'account', 'inventory_account', 'expense_type', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function group() { return $this->belongsTo(ProductGroup::class, 'product_group_id'); }
}
