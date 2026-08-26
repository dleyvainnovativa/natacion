<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lane extends Model
{
    protected $fillable = ['pool_id', 'label', 'position'];
    public function pool()     { return $this->belongsTo(Pool::class); }
    public function slots()    { return $this->hasMany(ScheduleSlot::class); }
    public function sessions() { return $this->hasMany(ClassSession::class); }
}
