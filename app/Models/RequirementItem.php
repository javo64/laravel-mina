<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequirementItem extends Model
{
    protected $fillable = ['requirement_id', 'product_id', 'product_name', 'category', 'description', 'quantity', 'unit', 'priority', 'cost_center_id', 'cost_center', 'approval_status', 'decision_at', 'decision_by'];
    protected function casts(): array { return ['decision_at' => 'datetime']; }
    public function requirement() { return $this->belongsTo(Requirement::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function costCenter() { return $this->belongsTo(CostCenter::class); }
    public function purchaseOrderItems() { return $this->hasMany(PurchaseOrderItem::class); }
    public function decisionMaker() { return $this->belongsTo(User::class, 'decision_by'); }
}
