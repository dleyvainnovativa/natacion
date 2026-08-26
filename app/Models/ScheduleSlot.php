<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ScheduleSlot extends Model
{
    protected $fillable = [
        'program_id', 'instructor_id', 'lane_id',
        'weekday', 'start_time', 'duration_min', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function program()    { return $this->belongsTo(Program::class); }
    public function instructor() { return $this->belongsTo(Instructor::class); }
    public function lane()       { return $this->belongsTo(Lane::class); }
    public function sessions()   { return $this->hasMany(ClassSession::class); }

    /** Roster recurrente: socios que asisten a este slot cada semana. */
    public function members()
    {
        return $this->belongsToMany(Member::class, 'slot_members')->withTimestamps();
    }

    /**
     * Construye el datetime de inicio de este slot para la semana que contiene
     * $reference. weekday es ISO (1=lunes ... 7=domingo).
     */
    public function startsAtForWeek(Carbon $reference): Carbon
    {
        $date = $reference->copy()->startOfWeek(Carbon::MONDAY)
            ->addDays($this->weekday - 1);

        [$h, $m] = array_pad(explode(':', (string) $this->start_time), 2, 0);

        return $date->setTime((int) $h, (int) $m, 0);
    }
}
