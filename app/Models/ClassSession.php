<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    protected $fillable = [
        'schedule_slot_id', 'program_id', 'lane_id',
        'scheduled_instructor_id', 'actual_instructor_id',
        'starts_at', 'duration_min', 'status', 'is_modified', 'notes',
    ];

    protected $casts = [
        'starts_at'   => 'datetime',
        'is_modified' => 'boolean',
    ];

    public function slot()                { return $this->belongsTo(ScheduleSlot::class, 'schedule_slot_id'); }
    public function program()             { return $this->belongsTo(Program::class); }
    public function lane()                { return $this->belongsTo(Lane::class); }
    public function scheduledInstructor() { return $this->belongsTo(Instructor::class, 'scheduled_instructor_id'); }
    public function actualInstructor()    { return $this->belongsTo(Instructor::class, 'actual_instructor_id'); }
    public function members()             { return $this->belongsToMany(Member::class, 'session_members')->withTimestamps(); }
    public function memberAttendances()   { return $this->hasMany(MemberAttendance::class); }
    public function instructorAttendance(){ return $this->hasOne(InstructorAttendance::class); }

    public function endsAt(): Carbon
    {
        return $this->starts_at->copy()->addMinutes($this->duration_min);
    }

    /** Cupo restante contra la capacidad del programa (avisa, no bloquea). */
    public function seatsRemaining(): int
    {
        return $this->program->lane_capacity - $this->members()->count();
    }

    public function isOverCapacity(): bool
    {
        return $this->seatsRemaining() < 0;
    }

    /** Sesiones dentro de una semana (lunes-domingo que contiene $ref). */
    public function scopeForWeek(Builder $query, Carbon $ref): Builder
    {
        return $query->whereBetween('starts_at', [
            $ref->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
            $ref->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
        ]);
    }
}
