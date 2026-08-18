<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Requirement extends Model
{
    protected $fillable = ['code', 'requested_at', 'responsible', 'project', 'area', 'priority', 'status', 'decision_at', 'decision_by'];
    protected function casts(): array { return ['requested_at' => 'date', 'decision_at' => 'datetime']; }
    public function items() { return $this->hasMany(RequirementItem::class); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class, 'decision_by'); }
}
