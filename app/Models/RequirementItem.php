<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequirementItem extends Model
{
    protected $fillable = ['requirement_id', 'product_id', 'product_name', 'category', 'description', 'quantity', 'unit', 'priority'];
    public function requirement() { return $this->belongsTo(Requirement::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
