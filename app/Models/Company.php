<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name', 'legal_name', 'ruc', 'code', 'is_active'];
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function branches() { return $this->hasMany(Branch::class)->orderBy('name'); }
}
