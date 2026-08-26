<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorAttendance extends Model
{
    protected $fillable = [
        'class_session_id', 'instructor_id', 'status',
        'substitute_instructor_id', 'marked_by', 'marked_at',
    ];
    protected $casts = ['marked_at' => 'datetime'];

    public function session()    { return $this->belongsTo(ClassSession::class, 'class_session_id'); }
    public function instructor() { return $this->belongsTo(Instructor::class); }
    public function substitute() { return $this->belongsTo(Instructor::class, 'substitute_instructor_id'); }
}
