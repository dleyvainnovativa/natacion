<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = [
        'slug', 'name', 'audience', 'age_range', 'duration_min',
        'lane_capacity', 'icon', 'color', 'summary', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function prices()          { return $this->hasMany(ProgramPrice::class); }
    public function membershipTypes() { return $this->hasMany(MembershipType::class); }
    public function slots()           { return $this->hasMany(ScheduleSlot::class); }

    public function isKids(): bool   { return $this->audience === 'kids'; }
}
