<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['code', 'barcode', 'warehouse', 'name', 'secondary_name', 'description', 'type', 'category', 'unit', 'currency', 'stock', 'min_stock', 'price', 'includes_tax', 'tax_affectation', 'is_active'];
    protected function casts(): array { return ['price' => 'decimal:2', 'includes_tax' => 'boolean', 'is_active' => 'boolean']; }
}
