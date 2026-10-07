<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasActiveCompany;

class ProductReception extends Model
{
    use HasActiveCompany;
    protected $fillable = [
        'company_id', 'purchase_order_id', 'code', 'received_at', 'supplier', 'document_reference', 'warehouse', 'notes', 'received_by',
        'guide_number', 'guide_file', 'invoice_number', 'invoice_file', 'order_number', 'order_file',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'date'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductReceptionItem::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
