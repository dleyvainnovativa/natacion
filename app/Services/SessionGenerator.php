<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\ScheduleSlot;
use App\Models\ScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Materializa las sesiones fechadas de una semana a partir de los slots
 * activos (la plantilla recurrente).
 *
 * Reglas:
 *  - Idempotente: si ya existe una sesión para (slot, fecha/hora), no la
 *    duplica.
 *  - Respeta lo manual: una sesión con is_modified = true (movida o editada
 *    "solo esta fecha") NO se regenera ni se pisa.
 *  - Hereda el roster: cada sesión nueva copia los socios del roster del slot
 *    hacia session_members.
 *  - actual_instructor_id arranca igual al programado; el coordinador lo
 *    cambia si hay suplente (T3).
 */
class SessionGenerator
{
    /**
     * Genera las sesiones de la semana que contiene $reference.
     *
     * La semana puede cruzar dos meses (p. ej. 31 ago – 6 sep). Por eso el mes
     * se decide POR DÍA: para cada día de la semana se resuelve la plantilla de
     * su mes (creándola/clonando si no existe) y se materializan sus slots de
     * ese día de la semana.
     *
     * @return array{created:int, skipped:int}
     */
    public function generateWeek(Carbon $reference): array
    {
        $created = 0;
        $skipped = 0;

        $weekStart = $reference->copy()->startOfWeek(Carbon::MONDAY);

        DB::transaction(function () use ($weekStart, &$created, &$skipped) {
            // Cache de plantillas por "YYYY-MM" para no resolver dos veces.
            $templateCache = [];

            for ($i = 0; $i < 7; $i++) {
                $day = $weekStart->copy()->addDays($i);
                $iso = $day->isoWeekday();                 // 1..7
                $key = $day->format('Y-m');

                $template = $templateCache[$key]
                    ??= ScheduleTemplate::resolveFor($day);

                // Slots activos de ESTA plantilla para ESTE día de la semana.
                $slots = ScheduleSlot::with('members:id')
                    ->where('schedule_template_id', $template->id)
                    ->where('active', true)
                    ->where('weekday', $iso)
                    ->get();

                foreach ($slots as $slot) {
                    [$created, $skipped] = $this->materialize($slot, $day, $created, $skipped);
                }
            }
        });

        return compact('created', 'skipped');
    }

    /**
     * Genera las sesiones de UN slot desde $from hasta $to (inclusive), solo en
     * su día de la semana. Idempotente. Se usa al crear/editar un slot para que
     * la clase aparezca de inmediato en la semana actual y las siguientes del
     * mes, sin regenerar el resto.
     *
     * @return array{created:int, skipped:int}
     */
    public function generateForSlot(ScheduleSlot $slot, Carbon $from, Carbon $to): array
    {
        if (! $slot->active) {
            return ['created' => 0, 'skipped' => 0];
        }

        $created = 0;
        $skipped = 0;

        $slot->loadMissing('members:id');

        DB::transaction(function () use ($slot, $from, $to, &$created, &$skipped) {
            $day = $from->copy()->startOfDay();
            $end = $to->copy()->endOfDay();

            while ($day->lte($end)) {
                if ($day->isoWeekday() === (int) $slot->weekday) {
                    [$created, $skipped] = $this->materialize($slot, $day, $created, $skipped);
                }
                $day->addDay();
            }
        });

        return compact('created', 'skipped');
    }

    /**
     * Crea la sesión de $slot en la fecha $day si no existe ya. Devuelve los
     * contadores actualizados. NO abre transacción (la abre quien llama).
     *
     * @return array{0:int,1:int} [created, skipped]
     */
    private function materialize(ScheduleSlot $slot, Carbon $day, int $created, int $skipped): array
    {
        [$h, $m] = array_pad(explode(':', (string) $slot->start_time), 2, 0);
        $startsAt = $day->copy()->setTime((int) $h, (int) $m, 0);

        $exists = ClassSession::where('schedule_slot_id', $slot->id)
            ->where('starts_at', $startsAt)
            ->exists();

        if ($exists) {
            return [$created, $skipped + 1];
        }

        $session = ClassSession::create([
            'schedule_slot_id'        => $slot->id,
            'program_id'              => $slot->program_id,
            'lane_id'                 => $slot->lane_id,
            'scheduled_instructor_id' => $slot->instructor_id,
            'actual_instructor_id'    => $slot->instructor_id,
            'starts_at'               => $startsAt,
            'duration_min'            => $slot->duration_min,
            'status'                  => 'scheduled',
            'is_modified'             => false,
        ]);

        $memberIds = $slot->members->pluck('id')->all();
        if ($memberIds) {
            $session->members()->sync($memberIds);
        }

        return [$created + 1, $skipped];
    }
}
