<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaintenanceLog extends Model
{
    protected $fillable = [
        'pool_id', 'title', 'notes', 'status', 'scheduled_for', 'created_by',
    ];

    protected $casts = ['scheduled_for' => 'date'];

    public function pool()    { return $this->belongsTo(Pool::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function isDone(): bool { return $this->status === 'done'; }

    /** Pendiente y con fecha programada ya pasada. */
    public function isOverdue(): bool
    {
        return $this->status === 'open'
            && $this->scheduled_for
            && $this->scheduled_for->isPast();
    }

    public function scopeOpen(Builder $q): Builder { return $q->where('status', 'open'); }
    public function scopeDone(Builder $q): Builder { return $q->where('status', 'done'); }
}
