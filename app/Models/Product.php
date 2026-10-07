<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasActiveCompany;

class Product extends Model
{
    use HasActiveCompany;
    protected $fillable = ['company_id', 'product_group_id', 'product_subgroup_id', 'code', 'barcode', 'warehouse', 'name', 'secondary_name', 'description', 'type', 'operation_type', 'category', 'unit', 'currency', 'stock', 'min_stock', 'price', 'includes_tax', 'tax_affectation', 'is_active'];
    protected function casts(): array { return ['price' => 'decimal:2', 'includes_tax' => 'boolean', 'is_active' => 'boolean']; }
    public function productGroup() { return $this->belongsTo(ProductGroup::class); }
    public function productSubgroup() { return $this->belongsTo(ProductSubgroup::class); }
}
