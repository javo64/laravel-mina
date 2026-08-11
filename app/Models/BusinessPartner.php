<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessPartner extends Model
{
    protected $fillable = [
        'type', 'document_type', 'document_number', 'name', 'trade_name',
        'address', 'district', 'province', 'department', 'phone', 'email',
        'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
