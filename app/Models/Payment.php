<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'member_id', 'concept', 'amount', 'paid_on',
        'period_start', 'period_end', 'recorded_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'paid_on'      => 'date',
        'period_start' => 'date',
        'period_end'   => 'date',
    ];

    public function member()   { return $this->belongsTo(Member::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
