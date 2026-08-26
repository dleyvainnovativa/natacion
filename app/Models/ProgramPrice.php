<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramPrice extends Model
{
    protected $fillable = ['program_id', 'tier_label', 'concept', 'days_per_week', 'amount'];
    protected $casts = ['amount' => 'decimal:2'];

    public function program() { return $this->belongsTo(Program::class); }
}
