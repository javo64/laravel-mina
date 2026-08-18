<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportForm extends Model
{
    protected $fillable = ['name','description','scope','is_active','use_gps','evaluator_location','allow_export','exact_search','allow_update','auto_collapse','created_by'];
    protected function casts(): array { return ['is_active'=>'boolean','use_gps'=>'boolean','evaluator_location'=>'boolean','allow_export'=>'boolean','exact_search'=>'boolean','allow_update'=>'boolean','auto_collapse'=>'boolean']; }
    public function fields() { return $this->hasMany(DailyReportField::class)->orderBy('position'); }
    public function users() { return $this->belongsToMany(User::class, 'daily_report_form_user'); }
    public function reports() { return $this->hasMany(DailyReport::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
