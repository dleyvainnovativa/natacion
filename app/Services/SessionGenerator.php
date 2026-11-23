<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\ScheduleSlot;
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
     * Horizonte por defecto de materialización hacia adelante (en semanas).
     * Con el horario "para siempre" no generamos infinito: mantenemos siempre
     * las próximas ~8 semanas creadas. El cron semanal corre el horizonte y así
     * la ventana avanza sola.
     */
    public const HORIZON_WEEKS = 8;

    /**
     * Materializa el horizonte hacia adelante desde HOY: la semana actual y las
     * siguientes HORIZON_WEEKS. Idempotente. Es lo que corre el cron semanal en
     * el modelo "para siempre".
     *
     * @return array{created:int, skipped:int}
     */
    public function generateHorizon(?int $weeks = null): array
    {
        $weeks = $weeks ?? self::HORIZON_WEEKS;
        $created = 0;
        $skipped = 0;

        $start = Carbon::now()->startOfWeek(Carbon::MONDAY);
        for ($w = 0; $w <= $weeks; $w++) {
            $r = $this->generateWeek($start->copy()->addWeeks($w));
            $created += $r['created'];
            $skipped += $r['skipped'];
        }

        return compact('created', 'skipped');
    }

    /**
     * Genera las sesiones de la semana que contiene $reference a partir de las
     * clases recurrentes (slots) VIGENTES ese día. Horario "para siempre": ya no
     * hay plantillas por mes; cada slot vale desde effective_from hasta
     * effective_to (null = para siempre).
     *
     * @return array{created:int, skipped:int}
     */
    public function generateWeek(Carbon $reference): array
    {
        $created = 0;
        $skipped = 0;

        $weekStart = $reference->copy()->startOfWeek(Carbon::MONDAY);

        DB::transaction(function () use ($weekStart, &$created, &$skipped) {
            for ($i = 0; $i < 7; $i++) {
                $day = $weekStart->copy()->addDays($i);
                $iso = $day->isoWeekday();   // 1..7

                $slots = ScheduleSlot::with('members:id')
                    ->where('active', true)
                    ->where('weekday', $iso)
                    ->whereDate('effective_from', '<=', $day->toDateString())
                    ->where(fn ($q) => $q->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $day->toDateString()))
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
    /**
     * Materializa un slot recién creado/editado a lo largo del horizonte: desde
     * HOY (o su effective_from si es futuro) hasta HOY + HORIZON_WEEKS. Así la
     * clase "para siempre" aparece de inmediato esta semana y las próximas.
     *
     * @return array{created:int, skipped:int}
     */
    public function generateForSlotForward(ScheduleSlot $slot, ?int $weeks = null): array
    {
        $weeks = $weeks ?? self::HORIZON_WEEKS;
        $from = Carbon::now()->startOfWeek(Carbon::MONDAY);
        if ($slot->effective_from && $slot->effective_from->gt($from)) {
            $from = $slot->effective_from->copy()->startOfWeek(Carbon::MONDAY);
        }
        $to = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks($weeks)->endOfWeek(Carbon::SUNDAY);

        return $this->generateForSlot($slot, $from, $to);
    }

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
     * Elimina las sesiones FUTURAS (de $from en adelante) de un slot que NO
     * tengan asistencia registrada. Se usa al quitar una clase del horario base:
     * el calendario deja de mostrarla hacia adelante, pero el histórico y
     * cualquier sesión con asistencia se conservan.
     */
    public function pruneFutureForSlot(ScheduleSlot $slot, ?Carbon $from = null): int
    {
        $from = ($from ?? Carbon::now()->startOfDay())->copy()->startOfDay();

        $ids = ClassSession::where('schedule_slot_id', $slot->id)
            ->where('starts_at', '>=', $from)
            ->whereDoesntHave('memberAttendances')
            ->whereDoesntHave('instructorAttendance')
            ->pluck('id')
            ->all();

        return $this->deleteSessions($ids);
    }

    /**
     * Borra sesiones por id junto con sus inscripciones (session_members). No
     * toca asistencia porque sólo se invoca con sesiones sin asistencia.
     *
     * @param  array<int>  $ids
     */
    public function deleteSessions(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        DB::transaction(function () use ($ids) {
            DB::table('session_members')->whereIn('class_session_id', $ids)->delete();
            ClassSession::whereIn('id', $ids)->delete();
        });

        return count($ids);
    }

    /**
     * Propaga el instructor (y co-maestro) de un slot a sus sesiones FUTURAS ya
     * generadas, para que el calendario y la asistencia coincidan con lo que dice
     * la plantilla. Se usa al editar el instructor de un slot y en el comando de
     * reconciliación.
     *
     * Sólo toca sesiones status='scheduled' + is_modified=false + de hoy en
     * adelante. Así NUNCA pisa:
     *   - suplentes / asistencia ya registrada  → esas quedan status='held'
     *   - clases movidas o editadas a mano       → is_modified=true
     *   - clases canceladas / pasadas            → status!='scheduled'
     *
     * @return int  sesiones actualizadas (o que cambiarían, si $dryRun)
     */
    public function syncInstructorToFutureSessions(ScheduleSlot $slot, ?Carbon $from = null, bool $dryRun = false): int
    {
        // Compatibilidad: ahora delega en la sincronización completa (hora +
        // instructor). Mantiene el nombre por si algo externo lo llama.
        return $this->syncSlotToFutureSessions($slot, $from, $dryRun);
    }

    /**
     * Propaga los atributos de un slot a sus sesiones FUTURAS ya generadas, para
     * que el calendario y la asistencia coincidan con lo que dice la plantilla.
     * Sincroniza: HORA (conservando la fecha), duración, instructor y co-maestro.
     * Se usa al editar un slot y en `schedule:fix-sessions`.
     *
     * Sólo toca sesiones status='scheduled' + is_modified=false + de hoy en
     * adelante. Así NUNCA pisa:
     *   - suplentes / asistencia ya registrada  → esas quedan status='held'
     *   - clases movidas o editadas a mano       → is_modified=true
     *   - clases canceladas / pasadas            → status!='scheduled'
     *
     * NO se propaga el CARRIL: las sesiones manejan el carril por su cuenta (se
     * reparten/rotan entre carriles) y forzarlo al del slot movería clases que
     * nadie pidió mover. Tampoco programa/nivel (cambiarlos a media serie
     * afectaría rosters/entitlements; para eso conviene recrear la clase).
     *
     * @return int  sesiones actualizadas (o que cambiarían, si $dryRun)
     */
    public function syncSlotToFutureSessions(ScheduleSlot $slot, ?Carbon $from = null, bool $dryRun = false): int
    {
        $from     = ($from ?? Carbon::now()->startOfDay())->copy()->startOfDay();
        $slotI    = $slot->instructor_id   !== null ? (int) $slot->instructor_id   : null;
        $slotI2   = $slot->instructor_id_2 !== null ? (int) $slot->instructor_id_2 : null;
        $slotTime = substr((string) $slot->start_time, 0, 5); // HH:MM
        $slotDur  = (int) $slot->duration_min;

        $sessions = ClassSession::where('schedule_slot_id', $slot->id)
            ->where('starts_at', '>=', $from)
            ->where('status', 'scheduled')
            ->where('is_modified', false)
            ->get();

        $changed = 0;
        foreach ($sessions as $s) {
            $update = [];

            // Hora: conservar la FECHA de la sesión, poner la HORA del slot.
            $wantStart = $s->starts_at->copy()->setTimeFromTimeString($slotTime);
            if (! $s->starts_at->equalTo($wantStart)) {
                $update['starts_at'] = $wantStart;
            }
            if ((int) $s->duration_min !== $slotDur) {
                $update['duration_min'] = $slotDur;
            }

            // Instructor: el programado sigue al slot; el "real" sólo si no hay
            // suplente (real == programado).
            $sched = $s->scheduled_instructor_id !== null ? (int) $s->scheduled_instructor_id : null;
            $act   = $s->actual_instructor_id    !== null ? (int) $s->actual_instructor_id    : null;
            $i2    = $s->instructor_id_2          !== null ? (int) $s->instructor_id_2         : null;
            if ($sched !== $slotI) {
                $update['scheduled_instructor_id'] = $slotI;
                if ($act === $sched) {
                    $update['actual_instructor_id'] = $slotI;
                }
            }
            if ($i2 !== $slotI2) {
                $update['instructor_id_2'] = $slotI2;
            }

            if ($update) {
                $changed++;
                if (! $dryRun) {
                    $s->update($update);
                }
            }
        }

        return $changed;
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

        // Respetar la vigencia del slot (horario para siempre).
        if ($slot->effective_from && $day->lt($slot->effective_from->copy()->startOfDay())) {
            return [$created, $skipped + 1];
        }
        if ($slot->effective_to && $day->gt($slot->effective_to->copy()->endOfDay())) {
            return [$created, $skipped + 1];
        }

        // Dedup por slot + DÍA (no por hora exacta): si este slot ya tiene una
        // sesión ese día, no crear otra. Esto evita el duplicado al arrastrar:
        // una sesión movida cambia su starts_at y quedaría "hueco" a la hora
        // original; sin este chequeo el generador la recrearía. Cubre también las
        // sesiones movidas a mano (is_modified) que siguen ocupando el día.
        $exists = ClassSession::where('schedule_slot_id', $slot->id)
            ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->exists();

        if ($exists) {
            return [$created, $skipped + 1];
        }

        $session = ClassSession::create([
            'schedule_slot_id'        => $slot->id,
            'program_id'              => $slot->program_id,
            'level_id'                => $slot->level_id,
            'lane_id'                 => $slot->lane_id,
            'scheduled_instructor_id' => $slot->instructor_id,
            'actual_instructor_id'    => $slot->instructor_id,
            'instructor_id_2'         => $slot->instructor_id_2,
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
