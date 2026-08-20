<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['branch_id', 'name', 'code', 'address', 'is_active', 'created_by'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function branch() { return $this->belongsTo(Branch::class); }
}
