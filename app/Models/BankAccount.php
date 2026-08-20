<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = ['business_partner_id', 'account_type', 'account_number', 'bank_name', 'holder_name', 'currency', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function partner()
    {
        return $this->belongsTo(BusinessPartner::class, 'business_partner_id');
    }
}
