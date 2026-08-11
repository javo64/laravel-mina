<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeasurementUnit extends Model
{
    protected $fillable = ['name', 'symbol', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
