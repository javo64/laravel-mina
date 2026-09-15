<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderQuotation extends Model
{
    protected $fillable = ['purchase_order_id', 'requirement_quotation_id', 'path', 'original_name', 'mime_type', 'size'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
