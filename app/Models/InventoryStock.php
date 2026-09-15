<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InventoryStock extends Model
{
    protected $fillable=['product_id','warehouse_id','quantity'];
    protected function casts(): array { return ['quantity'=>'decimal:2']; }
    public function product(){return $this->belongsTo(Product::class);}
    public function warehouse(){return $this->belongsTo(Warehouse::class);}
}
