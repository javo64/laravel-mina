<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuotationProcess extends Model {
 protected $fillable=['requirement_id','status','version','approval_observation','submitted_at','submitted_by','decided_at','decided_by'];
 protected function casts():array{return ['submitted_at'=>'datetime','decided_at'=>'datetime'];}
 public function requirement(){return $this->belongsTo(Requirement::class);} public function quotations(){return $this->hasMany(RequirementQuotation::class);} public function winner(){return $this->hasOne(RequirementQuotation::class)->where('is_winner',true);} public function submitter(){return $this->belongsTo(User::class,'submitted_by');} public function decisionMaker(){return $this->belongsTo(User::class,'decided_by');}
}
