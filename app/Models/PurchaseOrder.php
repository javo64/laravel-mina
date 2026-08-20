<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = ['code', 'destination_branch', 'destination_warehouse', 'document', 'series', 'number', 'supplier_id', 'bank_account_id', 'payment_condition', 'currency', 'area', 'tax_exempt', 'subtotal', 'tax', 'total', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['tax_exempt' => 'boolean', 'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function supplier() { return $this->belongsTo(BusinessPartner::class, 'supplier_id'); }
    public function bankAccount() { return $this->belongsTo(BankAccount::class); }
    public function items() { return $this->hasMany(PurchaseOrderItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
