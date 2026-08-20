<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'requirement_item_id', 'product_id', 'product_name', 'description', 'cost_center', 'quantity', 'unit', 'unit_price', 'total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function requirementItem() { return $this->belongsTo(RequirementItem::class); }
}
