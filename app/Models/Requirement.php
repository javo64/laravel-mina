<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Requirement extends Model
{
    protected $fillable = ['code', 'requested_at', 'responsible', 'project', 'area', 'priority', 'status', 'reviewed_at', 'reviewed_by', 'decision_at', 'decision_by'];
    protected function casts(): array { return ['requested_at' => 'date', 'reviewed_at' => 'datetime', 'decision_at' => 'datetime']; }
    public function items() { return $this->hasMany(RequirementItem::class); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class, 'decision_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function quotationProcess() { return $this->hasOne(QuotationProcess::class); }
    public function quotationProcesses() { return $this->hasMany(QuotationProcess::class); }
}
