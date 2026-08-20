<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function accounts() { return $this->hasMany(BankAccount::class); }
}
