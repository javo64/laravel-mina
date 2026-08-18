<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    protected $fillable = ['daily_report_form_id','user_id','reported_at','latitude','longitude','responses','status'];
    protected function casts(): array { return ['reported_at'=>'datetime','responses'=>'array','latitude'=>'decimal:7','longitude'=>'decimal:7']; }
    public function form() { return $this->belongsTo(DailyReportForm::class, 'daily_report_form_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
