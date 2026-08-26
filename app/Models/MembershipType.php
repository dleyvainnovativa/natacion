<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipType extends Model
{
    protected $fillable = [
        'raw_label', 'program_id', 'days_per_week', 'duration_min', 'default_fee', 'special',
    ];
    protected $casts = ['default_fee' => 'decimal:2', 'special' => 'boolean'];

    public function program() { return $this->belongsTo(Program::class); }
    public function members() { return $this->hasMany(Member::class); }
}
