<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'socio_number', 'last_name_1', 'last_name_2', 'first_name',
        'phone', 'email', 'membership_type_id', 'next_billing_date',
        'status', 'fee', 'notes',
    ];

    protected $casts = ['next_billing_date' => 'date', 'fee' => 'decimal:2'];

    public function membershipType() { return $this->belongsTo(MembershipType::class); }
    public function sessions()       { return $this->belongsToMany(ClassSession::class, 'session_members'); }
    public function payments()       { return $this->hasMany(Payment::class); }

    /** Slots recurrentes en los que el socio está inscrito (roster). */
    public function slotAssignments()
    {
        return $this->belongsToMany(ScheduleSlot::class, 'slot_members')->withTimestamps();
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name_1} {$this->last_name_2}");
    }

    /** Días/semana a los que tiene derecho su tipo de socio. */
    public function entitledDaysPerWeek(): ?int
    {
        return $this->membershipType?->days_per_week;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('socio_number', 'like', "%{$term}%")
              ->orWhere('first_name', 'like', "%{$term}%")
              ->orWhere('last_name_1', 'like', "%{$term}%")
              ->orWhere('last_name_2', 'like', "%{$term}%");
        });
    }
}
