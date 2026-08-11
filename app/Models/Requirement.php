<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    protected $fillable = ['code', 'requested_at', 'responsible', 'project', 'area', 'priority', 'status', 'decision_at', 'decision_by'];
    protected function casts(): array { return ['requested_at' => 'date', 'decision_at' => 'datetime']; }
    public function items() { return $this->hasMany(RequirementItem::class); }
}
