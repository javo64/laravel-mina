<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasActiveCompany;

class Warehouse extends Model
{
    use HasActiveCompany;
    protected $fillable = ['company_id', 'branch_id', 'name', 'code', 'address', 'is_active', 'created_by'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function branch() { return $this->belongsTo(Branch::class); }
}
