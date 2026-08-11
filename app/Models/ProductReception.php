<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReception extends Model
{
    protected $fillable = [
        'code', 'received_at', 'supplier', 'document_reference', 'warehouse', 'notes', 'received_by',
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
}
