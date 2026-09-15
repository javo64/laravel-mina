<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InventoryMovement extends Model
{
    protected $fillable=['code','occurred_at','type','product_id','source_warehouse_id','destination_warehouse_id','supplier_id','quantity','reference','notes','product_reception_item_id','created_by'];
    protected function casts(): array { return ['occurred_at'=>'datetime','quantity'=>'decimal:2']; }
    public function product(){return $this->belongsTo(Product::class);}
    public function sourceWarehouse(){return $this->belongsTo(Warehouse::class,'source_warehouse_id');}
    public function destinationWarehouse(){return $this->belongsTo(Warehouse::class,'destination_warehouse_id');}
    public function supplier(){return $this->belongsTo(BusinessPartner::class,'supplier_id');}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
}
