<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Una plantilla de horario para un mes concreto (year + month). Agrupa los
 * schedule_slots de ese mes. Al generar sesiones, el generador usa la plantilla
 * del mes de la semana objetivo.
 */
class ScheduleTemplate extends Model
{
    protected $fillable = ['year', 'month', 'label'];

    protected $casts = [
        'year'  => 'integer',
        'month' => 'integer',
    ];

    public function slots()
    {
        return $this->hasMany(ScheduleSlot::class);
    }

    /** Etiqueta legible, p. ej. "Julio 2026". */
    public function getDisplayLabelAttribute(): string
    {
        if ($this->label) {
            return $this->label;
        }
        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                  7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        return ($meses[$this->month] ?? $this->month) . ' ' . $this->year;
    }

    /** Clave "YYYY-MM" para URLs y selects. */
    public function getKeyMonthAttribute(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    /** Encuentra la plantilla del mes de $date (o null). */
    public static function forDate(Carbon $date): ?self
    {
        return static::where('year', $date->year)->where('month', $date->month)->first();
    }

    /**
     * Devuelve la plantilla del mes de $date. Si no existe, la crea:
     *  - clonando la plantilla del mes ANTERIOR más reciente (con sus slots y
     *    rosters), si existe;
     *  - vacía, si no hay ninguna previa.
     *
     * Es la pieza que hace "cada mes tiene su plantilla" con auto-clonado.
     */
    public static function resolveFor(Carbon $date): self
    {
        $existing = static::forDate($date);
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($date) {
            $template = static::create([
                'year'  => $date->year,
                'month' => $date->month,
            ]);

            // Buscar la plantilla previa más reciente para clonar.
            $source = static::query()
                ->where(function ($q) use ($date) {
                    $q->where('year', '<', $date->year)
                      ->orWhere(fn ($w) => $w->where('year', $date->year)->where('month', '<', $date->month));
                })
                ->orderByDesc('year')->orderByDesc('month')
                ->first();

            if ($source) {
                $template->cloneSlotsFrom($source);
            }

            return $template;
        });
    }

    /** Copia los slots (y sus rosters) de otra plantilla dentro de esta. */
    public function cloneSlotsFrom(self $source): void
    {
        $source->loadMissing('slots.members:id');

        foreach ($source->slots as $slot) {
            $copy = $this->slots()->create([
                'program_id'   => $slot->program_id,
                'instructor_id'=> $slot->instructor_id,
                'lane_id'      => $slot->lane_id,
                'weekday'      => $slot->weekday,
                'start_time'   => $slot->start_time,
                'duration_min' => $slot->duration_min,
                'active'       => $slot->active,
                'source'       => $slot->source,
            ]);

            $memberIds = $slot->members->pluck('id')->all();
            if ($memberIds) {
                $copy->members()->sync($memberIds);
            }
        }
    }
}
