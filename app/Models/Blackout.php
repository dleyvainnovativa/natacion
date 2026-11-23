<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Día festivo/inhábil o cierre por mantenimiento. Ver migración create_blackouts.
 */
class Blackout extends Model
{
    protected $fillable = ['date', 'start_min', 'end_min', 'type', 'reason', 'created_by'];

    protected $casts = [
        'date'      => 'date',
        'start_min' => 'integer',
        'end_min'   => 'integer',
    ];

    public function isWholeDay(): bool
    {
        return $this->start_min === null;
    }

    public function label(): string
    {
        return $this->type === 'mantenimiento' ? 'Mantenimiento' : 'Festivo';
    }

    public function rangeLabel(): string
    {
        if ($this->isWholeDay()) {
            return 'Todo el día';
        }

        return sprintf('%02d:%02d–%02d:%02d',
            intdiv($this->start_min, 60), $this->start_min % 60,
            intdiv($this->end_min, 60), $this->end_min % 60);
    }

    /** ¿Este cierre cubre una franja [sStart, sEnd) en minutos desde medianoche? */
    public function coversMinutes(int $sStart, int $sEnd): bool
    {
        if ($this->start_min === null) {
            return true; // todo el día
        }

        return $sStart < $this->end_min && $sEnd > $this->start_min; // solapan
    }

    /**
     * ¿La sesión cae dentro de alguno de los cierres dados? La colección debe ser
     * de cierres del MISMO día (se compara solo la hora).
     *
     * @param  Collection<int, Blackout>|iterable<Blackout>  $blackouts
     */
    public static function covers(\App\Models\ClassSession $session, iterable $blackouts): bool
    {
        $start = $session->starts_at->hour * 60 + $session->starts_at->minute;
        $end   = $start + (int) $session->duration_min;

        foreach ($blackouts as $b) {
            if ($b->date->toDateString() === $session->starts_at->toDateString()
                && $b->coversMinutes($start, $end)) {
                return true;
            }
        }

        return false;
    }
}
