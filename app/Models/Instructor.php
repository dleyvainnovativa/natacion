<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    protected $fillable = ['user_id', 'name', 'pay_per_class', 'active'];
    protected $casts = ['pay_per_class' => 'decimal:2', 'active' => 'boolean'];

    public function user()             { return $this->belongsTo(User::class); }
    public function slots()            { return $this->hasMany(ScheduleSlot::class); }
    public function scheduledSessions(){ return $this->hasMany(ClassSession::class, 'scheduled_instructor_id'); }
    public function taughtSessions()   { return $this->hasMany(ClassSession::class, 'actual_instructor_id'); }
}
