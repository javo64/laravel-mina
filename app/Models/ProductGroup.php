<?php

namespace App\Models;

use App\Models\Concerns\HasActiveCompany;
use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    use HasActiveCompany;

    protected $fillable = ['company_id', 'code', 'name', 'item_type', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function subgroups() { return $this->hasMany(ProductSubgroup::class)->orderBy('code'); }
}
