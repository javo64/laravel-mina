<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RequirementQuotation extends Model {
 protected $fillable=['quotation_process_id','supplier_id','path','original_name','mime_type','size','amount','currency','is_winner'];
 protected function casts():array{return ['amount'=>'decimal:2','is_winner'=>'boolean'];}
 public function process(){return $this->belongsTo(QuotationProcess::class,'quotation_process_id');} public function supplier(){return $this->belongsTo(BusinessPartner::class,'supplier_id');}
}
