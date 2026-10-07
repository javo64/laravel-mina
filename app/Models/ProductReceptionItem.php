<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReceptionItem extends Model
{
    protected $fillable = ['purchase_order_item_id', 'product_id', 'product_code', 'product_name', 'unit', 'quantity', 'stock_before', 'stock_after'];

    public function reception(): BelongsTo
    {
        return $this->belongsTo(ProductReception::class, 'product_reception_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
