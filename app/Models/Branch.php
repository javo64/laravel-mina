<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['company_id', 'name', 'code', 'address', 'is_active', 'created_by'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function warehouses() { return $this->hasMany(Warehouse::class)->orderBy('name'); }
    public function company() { return $this->belongsTo(Company::class); }
}
