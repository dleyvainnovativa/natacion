<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAttendance extends Model
{
    protected $fillable = [
        'class_session_id', 'member_id', 'status', 'marked_by', 'marked_at',
    ];
    protected $casts = ['marked_at' => 'datetime'];

    public function session() { return $this->belongsTo(ClassSession::class, 'class_session_id'); }
    public function member()  { return $this->belongsTo(Member::class); }
}
