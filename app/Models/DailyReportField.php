<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportField extends Model
{
    protected $fillable = ['daily_report_form_id','field_key','section','name','type','help_text','options','formula','settings','is_required','copy_previous','position'];
    protected function casts(): array { return ['options'=>'array','settings'=>'array','is_required'=>'boolean','copy_previous'=>'boolean']; }
    public function form() { return $this->belongsTo(DailyReportForm::class, 'daily_report_form_id'); }
}
